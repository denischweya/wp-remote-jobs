/**
 * Job List View Script
 */

// Use WordPress's global jQuery
jQuery(document).ready(function ($) {
	// Debug: Check if required variables are available
	
	// Flag to track if we're ready to make AJAX requests
	let ajaxReady = false;
	
	// Fallback: Create remjobsAjax if it doesn't exist
	if (typeof remjobsAjax === "undefined") {
		console.warn("remjobsAjax not found, creating fallback...");
		
		// Create a basic fallback object
		window.remjobsAjax = {
			ajaxurl: "", // Will be set dynamically
			nonce: "" // We'll try to get this from the server
		};
		
		// Try to get the AJAX URL from WordPress if available
		if (typeof ajaxurl !== "undefined") {
			window.remjobsAjax.ajaxurl = ajaxurl;
		} else if (typeof wpApiSettings !== "undefined" && wpApiSettings.root) {
			// Use REST API as fallback for Ajax URL determination
			const baseUrl = wpApiSettings.root.replace(/wp-json.*$/, '');
			window.remjobsAjax.ajaxurl = baseUrl + 'wp-admin/admin-ajax.php';
		} else {
			// Last resort: use relative path and let browser resolve
			window.remjobsAjax.ajaxurl = './wp-admin/admin-ajax.php';
		}
		
		// Try to get a nonce from the server
		$.ajax({
			url: window.remjobsAjax.ajaxurl,
			type: "POST",
			data: { action: "get_filter_nonce" },
			success: function(response) {
				if (response.success && response.data.nonce) {
					window.remjobsAjax.nonce = response.data.nonce;
					window.remjobsAjax.ajaxurl = response.data.ajaxurl;
					ajaxReady = true;
				} else {
					console.warn("Failed to get nonce from server:", response);
					// Still set ajaxReady to true to allow requests (debug mode should handle this)
					ajaxReady = true;
				}
			},
			error: function(xhr, status, error) {
				console.warn("Error getting nonce from server:", status, error);
				// Still set ajaxReady to true to allow requests (debug mode should handle this)
				ajaxReady = true;
			}
		});
		
	} else {
		// remjobsAjax is already available, we're ready
		ajaxReady = true;
	}
	
	// Cache DOM elements
	const $jobList = $("#jobs-list");
	const $searchInput = $("#job-search");
	const $categorySelect = $("#job-category");
	const $locationSelect = $("#job-location");
	const $skillsSelect = $("#job-skills");
	const $filterButton = $("#clear-filters");
	const $jobSearchForm = $(".job-search-form");

	// Function to update jobs list
	function updateJobs() {
		
		// Check if AJAX system is ready
		if (!ajaxReady) {
			// Wait and retry
			setTimeout(function() {
				updateJobs();
			}, 500);
			return;
		}
		
		// Check if required variables are available
		if (typeof remjobsAjax === 'undefined') {
			return;
		}

		// Get current filter values
		const search = $searchInput.val() || "";
		const category = $categorySelect.val() || "";
		const location = $locationSelect.val() || "";
		const skills = $skillsSelect.val() || "";

		// Detect current layout from the active layout button instead of DOM classes
		// (since DOM gets replaced during updates)
		let currentLayout = "grid"; // default
		const $activeLayoutButton = $(".layout-toggle button.active");
		if ($activeLayoutButton.length) {
			const buttonLayout = $activeLayoutButton.data("layout");
			if (buttonLayout) {
				currentLayout = buttonLayout;
			}
		}

		// Show loading state
		$jobList.html('<div class="loading">Loading jobs...</div>');

		// AJAX data
		const data = {
			action: "filter_jobs",
			nonce: remjobsAjax.nonce,
			search: search,
			category: category,
			location: location,
			skills: skills,
			layout: currentLayout
		};

		// Make AJAX request
		$.ajax({
			url: remjobsAjax.ajaxurl,
			type: "POST",
			data: data,
			beforeSend: function() {
			},
			success: function (response) {
				if (response.success) {
					// Update jobs list with new content
					$jobList.html(response.data.data);
				} else {
					// If nonce is expired, get a new one and retry
					$.ajax({
						url: remjobsAjax.ajaxurl,
						type: "POST",
						data: {
							action: 'get_filter_nonce'
						},
						success: function (nonceResponse) {
							if (nonceResponse.success) {
								window.remjobsAjax.nonce = nonceResponse.data.nonce;
								updateJobs(); // Retry with new nonce
							}
						}
					});
				}
			},
			error: function (xhr, status, error) {
				$jobList.html('<div class="error">Error loading jobs</div>');
			}
		});
	}

	// Event listeners
	if ($filterButton.length) {
		$filterButton.on("click", function (e) {
			e.preventDefault();
			$searchInput.val("").trigger("change"); // Clear search input
			$categorySelect.val("").trigger("change"); // Clear category
			$locationSelect.val("").trigger("change"); // Clear location
			$skillsSelect.val("").trigger("change"); // Clear skills
			updateJobs(); // Trigger update with new filters
		});
	}

	// Optional: Update on select change
	$categorySelect.on("change", debounce(function () {
		updateJobs();
	}, 500));

	$locationSelect.on("change", debounce(function () {
		updateJobs();
	}, 500));

	$skillsSelect.on("change", debounce(function () {
		updateJobs();
	}, 500));

	// Search input with debounce
	$searchInput.on("input", debounce(function () {
		updateJobs();
	}, 500));

	// Handle layout toggle if it exists
	const $layoutButtons = $(".layout-toggle button");
	if ($layoutButtons.length) {
		$layoutButtons.on("click", function () {
			const layout = $(this).data("layout");
			
			// Find the job listings container (the one with class job-listings-grid or job-listings-list)
			const $jobListingsContainer = $jobList.find('[class*="job-listings-"]');
			
			if ($jobListingsContainer.length) {
				// Remove existing layout classes and add the new one
				$jobListingsContainer.removeClass("job-listings-grid job-listings-list");
				$jobListingsContainer.addClass("job-listings-" + layout);
			}
			
			// Update button states
			$layoutButtons.removeClass("is-primary active").addClass("is-secondary");
			$(this).removeClass("is-secondary").addClass("is-primary active");
			
			// Update the jobs list with the new layout
			updateJobs();
		});
	}

	// Setup save job functionality
	setupSaveJobButtons();
});

/**
 * Debounce function to limit how often a function can be called
 */
function debounce(func, wait) {
	let timeout;
	return function executedFunction(...args) {
		const later = () => {
			clearTimeout(timeout);
			func(...args);
		};
		clearTimeout(timeout);
		timeout = setTimeout(later, wait);
	};
}

/**
 * Setup the save job functionality
 */
function setupSaveJobButtons() {
	const saveButtons = document.querySelectorAll(".save-job-button");

	saveButtons.forEach((button) => {
		button.addEventListener("click", (e) => {
			e.preventDefault();
			const jobId = button.dataset.jobId;
		});
	});
}

/**
 * Highlight premium jobs with crown icon
 */
document.addEventListener("DOMContentLoaded", function () {
	// Add premium job indicators
	const premiumJobs = document.querySelectorAll(
		".job-card.premium, .job-row.premium",
	);

	premiumJobs.forEach((job) => {
		const indicator = document.createElement("div");
		indicator.className = "premium-indicator";
		indicator.innerHTML = `
			<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
				<path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"></path>
			</svg>
		`;
		job.appendChild(indicator);
	});
});

/**
 * Save job functionality
 */
document.addEventListener("DOMContentLoaded", function () {
	const saveButtons = document.querySelectorAll(".save-job-button");

	saveButtons.forEach((button) => {
		button.addEventListener("click", function (e) {
			e.preventDefault();

			// Toggle filled/unfilled state
			const svg = button.querySelector("svg");

			if (svg.getAttribute("fill") === "none") {
				svg.setAttribute("fill", "currentColor");
				button.setAttribute("title", "Saved");

				// You would add the job ID to saved jobs in user preferences
				// This is a simplified example
			} else {
				svg.setAttribute("fill", "none");
				button.setAttribute("title", "Save job");

				// You would remove the job ID from saved jobs in user preferences
			}
		});
	});
});
