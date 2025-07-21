/**
 * Job List View Script
 */

// Use WordPress's global jQuery
jQuery(document).ready(function ($) {
	// Debug: Check if required variables are available
	console.log("Job List Script Loaded");
	console.log("remjobsAjax available:", typeof remjobsAjax !== "undefined");
	
	// Flag to track if we're ready to make AJAX requests
	let ajaxReady = false;
	
	// Fallback: Create remjobsAjax if it doesn't exist
	if (typeof remjobsAjax === "undefined") {
		console.warn("remjobsAjax not found, creating fallback...");
		
		// Create a basic fallback object
		window.remjobsAjax = {
			ajaxurl: "/wp-admin/admin-ajax.php", // Standard WordPress AJAX URL
			nonce: "" // We'll try to get this from the server
		};
		
		// Try to get the AJAX URL from WordPress if available
		if (typeof ajaxurl !== "undefined") {
			window.remjobsAjax.ajaxurl = ajaxurl;
		} else if (typeof wpApiSettings !== "undefined" && wpApiSettings.root) {
			// Use REST API as fallback
			window.remjobsAjax.ajaxurl = wpApiSettings.root + "wp/v2/";
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
					console.log("Successfully retrieved nonce from server:", window.remjobsAjax);
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
		
		console.log("Created fallback remjobsAjax:", window.remjobsAjax);
	} else {
		// remjobsAjax is already available, we're ready
		ajaxReady = true;
	}
	
	if (typeof remjobsAjax !== "undefined") {
		console.log("remjobsAjax:", remjobsAjax);
	} else {
		console.error("remjobsAjax is still not available after fallback!");
		console.log("Available global variables:", Object.keys(window));
	}

	// Cache DOM elements
	const $jobList = $("#jobs-list");
	const $searchInput = $("#job-search");
	const $categorySelect = $("#job-category");
	const $locationSelect = $("#job-location");
	const $skillsSelect = $("#job-skills");
	const $filterButton = $("#filter-jobs");
	const $jobSearchForm = $(".job-search-form");

	// Debug: Check if elements are found
	console.log("DOM Elements Found:");
	console.log("- Job List Container (#jobs-list):", $jobList.length);
	console.log("- Search Input (#job-search):", $searchInput.length);
	console.log("- Category Select (#job-category):", $categorySelect.length);
	console.log("- Location Select (#job-location):", $locationSelect.length);
	console.log("- Skills Select (#job-skills):", $skillsSelect.length);
	console.log("- Filter Button (#filter-jobs):", $filterButton.length);

	// Function to update jobs list
	function updateJobs() {
		console.log("=== updateJobs function called ===");
		
		// Check if AJAX system is ready
		if (!ajaxReady) {
			console.log("AJAX system not ready yet, waiting...");
			// Wait and retry
			setTimeout(function() {
				updateJobs();
			}, 500);
			return;
		}
		
		// Check if required variables are available
		if (typeof remjobsAjax === 'undefined') {
			console.error("remjobsAjax is not available in updateJobs function");
			return;
		}
		
		console.log("remjobsAjax in updateJobs:", remjobsAjax);

		// Get current filter values
		const search = $searchInput.val() || "";
		const category = $categorySelect.val() || "";
		const location = $locationSelect.val() || "";
		const skills = $skillsSelect.val() || "";

		console.log("Filter values:");
		console.log("- Search:", search);
		console.log("- Category:", category);
		console.log("- Location:", location);
		console.log("- Skills:", skills);

		// Detect current layout from the jobs list container
		const $layoutContainer = $jobList.find('[class*="job-listings-"]');
		let currentLayout = "grid"; // default
		if ($layoutContainer.length) {
			if ($layoutContainer.hasClass("job-listings-list")) {
				currentLayout = "list";
			} else if ($layoutContainer.hasClass("job-listings-grid")) {
				currentLayout = "grid";
			}
		}
		
		console.log("Current layout:", currentLayout);

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
			layout: currentLayout,
		};
		
		console.log("AJAX data being sent:", data);
		console.log("AJAX URL:", remjobsAjax.ajaxurl);
		
		// Check if we have a nonce
		if (!data.nonce || data.nonce === '') {
			console.warn("No nonce available, but proceeding (debug mode should handle this)");
		}

		// Make AJAX request
		$.ajax({
			url: remjobsAjax.ajaxurl,
			type: "POST",
			data: data,
			beforeSend: function() {
				console.log("AJAX request starting...");
			},
			success: function (response) {
				console.log("AJAX Success response:", response);
				
				if (response.success) {
					console.log("Response data:", response.data);
					$jobList.html(response.data.data);
					console.log("Jobs updated successfully. Found:", response.data.found, "posts");
				} else {
					console.error("AJAX returned success=false:", response);
					$jobList.html('<div class="error">Error loading jobs: ' + (response.data.message || 'Unknown error') + '</div>');
				}
			},
			error: function (xhr, status, error) {
				console.error("AJAX Error:");
				console.error("- Status:", status);
				console.error("- Error:", error);
				console.error("- Response:", xhr.responseText);
				console.error("- Status Code:", xhr.status);
				
				// If it's a nonce issue and we're in fallback mode, try to get a new nonce
				if (xhr.status === 400 && !remjobsAjax.nonce) {
					console.log("Trying to get a fresh nonce...");
					$.ajax({
						url: remjobsAjax.ajaxurl,
						type: "POST",
						data: { action: "get_filter_nonce" },
						success: function(nonceResponse) {
							if (nonceResponse.success && nonceResponse.data.nonce) {
								remjobsAjax.nonce = nonceResponse.data.nonce;
								console.log("Got fresh nonce, retrying filter request...");
								// Retry the original request
								setTimeout(updateJobs, 100);
								return;
							}
						}
					});
				}
				
				$jobList.html('<div class="error">Error loading jobs. Please try again.</div>');
			},
			complete: function() {
				console.log("AJAX request completed");
			}
		});
	}

	// Event listeners
	if ($filterButton.length) {
		$filterButton.on("click", function (e) {
			e.preventDefault();
			console.log("Filter button clicked");
			console.log("Current filter values:", {
				search: $searchInput.val(),
				category: $categorySelect.val(),
				skills: $skillsSelect.val(),
				location: $locationSelect.val(),
			});
			updateJobs();
		});
	}

	// Optional: Update on select change
	$searchInput.on("keyup", debounce(updateJobs, 500));
	$categorySelect.on("change", function () {
		console.log("Category changed to:", $(this).val());
		updateJobs();
	});
	$locationSelect.on("change", function () {
		console.log("Location changed to:", $(this).val());
		updateJobs();
	});
	$skillsSelect.on("change", function () {
		console.log("Skills changed to:", $(this).val());
		updateJobs();
	});

	// Debounce function for search input
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

	// Handle layout toggle if it exists
	const $layoutButtons = $(".layout-toggle button");
	if ($layoutButtons.length) {
		$layoutButtons.on("click", function () {
			const layout = $(this).data("layout");
			$jobList.removeClass("grid list").addClass(layout);
			$layoutButtons.removeClass("is-primary").addClass("is-secondary");
			$(this).removeClass("is-secondary").addClass("is-primary");
		});
	}

	// Get all job listing containers on the page
	const jobListingContainers = document.querySelectorAll(
		".job-listings-container",
	);

	if (!jobListingContainers.length) {
		return;
	}

	jobListingContainers.forEach((container) => {
		const containerId = container.id;
		const layoutButtons = container.querySelectorAll(".layout-button");
		const jobListingsWrapper = container.querySelector(
			'[class^="job-listings-"]',
		);

		// Only initialize if we have layout buttons and a wrapper
		if (!layoutButtons.length || !jobListingsWrapper) {
			return;
		}

		// Get the initial layout from container data attribute
		const initialLayout = container.dataset.layout || "grid";

		// Set initial layout from saved preference, defaulting to stored container data attribute
		let currentLayout = getLayoutPreference(containerId) || initialLayout;

		// Apply the initial layout
		applyLayout(currentLayout, jobListingsWrapper);

		// Mark the correct button as active
		updateActiveButton(layoutButtons, currentLayout);

		// Add event listeners to layout buttons
		layoutButtons.forEach((button) => {
			button.addEventListener("click", () => {
				const layout = button.dataset.layout;

				// Don't do anything if already in this layout
				if (layout === currentLayout) {
					return;
				}

				// Update the layout
				applyLayout(layout, jobListingsWrapper);

				// Mark this button as active
				updateActiveButton(layoutButtons, layout);

				// Save preference
				saveLayoutPreference(containerId, layout);

				// Update tracking variable
				currentLayout = layout;
			});
		});

		// Setup filter functionality if present
		setupFilters(container);
	});

	// Setup save job functionality
	setupSaveJobButtons();
});

/**
 * Apply the specified layout to the job listings wrapper
 *
 * @param {string} layout The layout to apply (grid or list)
 * @param {HTMLElement} wrapper The job listings wrapper element
 */
function applyLayout(layout, wrapper) {
	// Remove current layout class
	if (wrapper.className.includes("job-listings-")) {
		wrapper.className = wrapper.className.replace(
			/job-listings-(grid|list)/,
			`job-listings-${layout}`,
		);
	} else {
		wrapper.className = `job-listings-${layout}`;
	}
}

/**
 * Update which button is marked as active
 *
 * @param {NodeList} buttons Layout toggle buttons
 * @param {string} activeLayout The active layout
 */
function updateActiveButton(buttons, activeLayout) {
	buttons.forEach((button) => {
		if (button.dataset.layout === activeLayout) {
			button.classList.add("active");
		} else {
			button.classList.remove("active");
		}
	});
}

/**
 * Save layout preference to localStorage
 *
 * @param {string} containerId The ID of the job listings container
 * @param {string} layout The layout preference
 */
function saveLayoutPreference(containerId, layout) {
	if (typeof localStorage !== "undefined") {
		localStorage.setItem(`remjobs_layout_${containerId}`, layout);
	}
}

/**
 * Get saved layout preference from localStorage
 *
 * @param {string} containerId The ID of the job listings container
 * @return {string|null} The saved layout preference or null
 */
function getLayoutPreference(containerId) {
	if (typeof localStorage !== "undefined") {
		return localStorage.getItem(`remjobs_layout_${containerId}`);
	}
	return null;
}

/**
 * Setup the filters functionality
 *
 * @param {HTMLElement} container The job listings container
 */
function setupFilters(container) {
	const filterButton = container.querySelector(".filter-button");
	const categorySelect = container.querySelector(".category-select");
	const locationSelect = container.querySelector(".location-select");
	const skillsSelect = container.querySelector(".skills-select");
	const searchInput = container.querySelector(".search-input");

	if (!filterButton) {
		return;
	}

	// Get current URL params
	const urlParams = new URLSearchParams(window.location.search);

	// Set initial values from URL if they exist
	if (searchInput && urlParams.has("search")) {
		searchInput.value = urlParams.get("search");
	}

	if (categorySelect && urlParams.has("category")) {
		categorySelect.value = urlParams.get("category");
	}

	if (locationSelect && urlParams.has("location")) {
		locationSelect.value = urlParams.get("location");
	}

	if (skillsSelect && urlParams.has("skills")) {
		skillsSelect.value = urlParams.get("skills");
	}

	// Handle filter button click
	filterButton.addEventListener("click", () => {
		// Get filter values
		const category = categorySelect ? categorySelect.value : "";
		const location = locationSelect ? locationSelect.value : "";
		const skills = skillsSelect ? skillsSelect.value : "";
		const search = searchInput ? searchInput.value : "";

		// Here you would typically make an AJAX request to filter jobs
		// For now, we just reload with query parameters
		const url = new URL(window.location.href);

		if (category) {
			url.searchParams.set("category", category);
		} else {
			url.searchParams.delete("category");
		}

		if (location) {
			url.searchParams.set("location", location);
		} else {
			url.searchParams.delete("location");
		}

		if (skills) {
			url.searchParams.set("skills", skills);
		} else {
			url.searchParams.delete("skills");
		}

		if (search) {
			url.searchParams.set("search", search);
		} else {
			url.searchParams.delete("search");
		}

		window.location.href = url.toString();
	});
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

			if (!jobId) {
				return;
			}

			// Toggle saved state visually
			button.classList.toggle("saved");

			// TODO: Implement saving jobs to user preferences
			// This would typically involve an AJAX call to save the job
			console.log("Toggle saved state for job ID:", jobId);
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
