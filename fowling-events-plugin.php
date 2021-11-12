<?php
/**
 * Plugin Name:       Fowling Events Plugin
 * Plugin URI:        https://fairlypainless.com/
 * Description:       An uncomplicated events plugin built specifically for Fowling Warehouse.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Fairly Painless
 * Author URI:        https://github.com/JackRie/fowling-events-plugin
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fep
 */

//  Security Check
if ( ! defined( 'WPINC' ) ) {
    die;
}
// Define URL
define( 'FEP_URL', plugin_dir_url(__FILE__) );
// Define Directory Path (indludes trailing slash)
define( 'FEP_DIR', plugin_dir_path( __FILE__ ) );

// Create option in settings menu
function fep_admin_page() {
    global $fep_settings;
    $fep_settings = add_options_page( __('Fowling Events', 'fep'), __('Fowling Events', 'fep'), 'manage_options', 'fep', 'fep_render_admin');
}

add_action( 'admin_menu', 'fep_admin_page');

// Render HTML for plugin page accessed from settings menu
function fep_render_admin() { ?>
    <div class="wrap">
        <h2><?php _e('Fowling Events') ?></h2>
        <h3>Click the button below to update event dates manually</h3>
        <div id="refresh-button-container">
            <button
                type="button"
                class="button-primary"
                id="refresh-cache">Refresh Cache For Events
            </button>
            <span class="spinner" style="float: none;"></span>
        </div>
    </div>
<?php
}

// Load admin JavaScript
function fep_load_scripts($hook) {

    global $fep_settings;

    //only load this script for a certain URL page slug
    if($hook == $fep_settings) {
        wp_enqueue_script( 'fep-custom-admin-js', FEP_URL . 'inc/admin/js/fep-admin.js', ['jquery']);

        wp_localize_script('fep-custom-admin-js', 'fep_ajax_obj', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    } else {
        return;
    }

}

add_action( 'admin_enqueue_scripts', 'fep_load_scripts' );

/**
 * SETUP CRON JOB TO RUN DAILY AND FIRE EVENT CHECK FUNCTION
 */
register_activation_hook(__FILE__, 'fep_event_check_schedule');

function fep_event_check_schedule() {

    $timestamp = wp_next_scheduled('fep_event_check_hourly');

    if(!$timestamp) {
        wp_schedule_event(time(), 'hourly', 'fep_event_check_hourly');
    }

}

add_action( 'fep_event_check_hourly', 'fep_event_check' );

/**
 * REMOVE CRON JOB ON PLUGIN DEACTIVATION
 */
register_deactivation_hook( __FILE__, 'fep_remove_hourly_backup_schedule' );

function fep_remove_hourly_backup_schedule(){
  wp_clear_scheduled_hook( 'fep_event_check_hourly' );
}

/**
 * EVENT CHECK FUNCTION
 */
function fep_event_check() {
	// GET ALL EVENTS POSTS
	$args = array(
		'post_type' => 'event'
	);
	$event_posts = get_posts($args);
	// LOOP THROUGH ALL EVENTS POSTS
	foreach($event_posts as $post) {
		// SETUP THE POST DATA
		setup_postdata( $post );
		$event_type = get_field( 'event_type', $post->ID );
		$recur_day = get_field( 'recurring_day', $post->ID );
		$start_date = get_field( 'start_date', $post->ID );
		$updated_recur_date = date('Ymd', strtotime('next ' . $recur_day));
		$today = date('Ymd');
		// CHECK IF POST IS A RECURRING POST IF START DATE IS NOT EQUAL TO TODAY'S DATE AND
		// START DATE IS LESS THAN (IN THE PAST) THAN THE NEXT DATE THIS EVENT IS SET TO RECUR
		if ( $event_type == 'recur' && $start_date != $today && $start_date < $updated_recur_date ) {
			// UPDATE THE START DATE TO THE NEXT DATE THIS EVENT IS SET TO RECUR
			update_field('field_60f8548e23cef', $updated_recur_date, $post->ID );
		}
	}
	// RESET THE POST DATA
	wp_reset_postdata();
	// NEED TO INCLUDE WP_DIE FOR AJAX CALLBACK FUNCTION
	wp_die();
}

/**
 * THESE ACTIONS HOOKS ALLOW US TO RUN EVENT CHECK FUNCTION VIA AJAX
 */
add_action('wp_ajax_fep_event_check', 'fep_event_check');
add_action('wp_ajax_nopriv_fep_event_check', 'fep_event_check');

/**
 * CREATE SHORTCODE THAT RETURNS THE 5 SOONEST UPCOMING EVENTS
 */
function fep_create_shortcode_events($atts) {
    $atts = shortcode_atts( array(
        'number' => "-1"
    ), $atts );
	$today = date('Ymd');
    $args = array(
        'post_type' => 'event',
        'posts_per_page' => $atts['number'],
        'orderby' => 'start_date',
        'order' => 'ASC',
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => 'start_date',
                'compare' => '>=',
                'value' => $today,
                'type' => 'DATE'
            ),
            array(
                'key' => 'end_date',
                'compare' => '>=',
                'value' => $today,
                'type' => 'DATE'
            )
        )
    );
    $query = new WP_Query($args);
	$result .= '<div class="event-shortcode-container">';
    if($query->have_posts()) {
        while($query->have_posts()) {
			$query->the_post();
			ob_start();
				get_template_part( 'template-parts/content-loop', 'event' );
			$result .= ob_get_clean();
        }
        wp_reset_postdata();
    } else {
		$result .= '<div class="no-events">';
		$result .= '<h3>Sorry, there are no upcoming events at this time.<h3>';
		$result .= '</div>';
	}
	$result .= '</div>';

    return $result;
}

add_shortcode('fowling-events', 'fep_create_shortcode_events');