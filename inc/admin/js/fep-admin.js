;(function ($) {
	$(document).ready(function ($) {
		var btn = document.getElementById("refresh-cache")
		function refreshCache() {
			var spinner = document.querySelector("#refresh-button-container .spinner")
			spinner.style.visibility = "visible"
			$.ajax({
				url: fep_ajax_obj.ajax_url,
				type: "GET",
				data: {
					action: "fep_event_check",
				},
				success: function (response) {
					spinner.style.visibility = "hidden"
				},
				error: function (response) {
					console.log(response)
				},
			})
		}

		btn.addEventListener("click", refreshCache)
	})
})(jQuery)
