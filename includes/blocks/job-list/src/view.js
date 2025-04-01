/**
 * Job List View Script
 */
document.addEventListener('DOMContentLoaded', () => {
	// Get all job listing containers on the page
	const jobListingContainers = document.querySelectorAll('.job-listings-container');

	if (!jobListingContainers.length) {
		return;
	}

	jobListingContainers.forEach(container => {
		const containerId = container.id;
		const layoutButtons = container.querySelectorAll('.layout-button');
		const jobListingsWrapper = container.querySelector('[class^="job-listings-"]');

		// Only initialize if we have layout buttons and a wrapper
		if (!layoutButtons.length || !jobListingsWrapper) {
			return;
		}

		// Get the initial layout from container data attribute
		const initialLayout = container.dataset.layout || 'grid';
		
		// Set initial layout from saved preference, defaulting to stored container data attribute
		let currentLayout = getLayoutPreference(containerId) || initialLayout;
		
		// Apply the initial layout
		applyLayout(currentLayout, jobListingsWrapper);
		
		// Mark the correct button as active
		updateActiveButton(layoutButtons, currentLayout);

		// Add event listeners to layout buttons
		layoutButtons.forEach(button => {
			button.addEventListener('click', () => {
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
	if (wrapper.className.includes('job-listings-')) {
		wrapper.className = wrapper.className.replace(/job-listings-(grid|list)/, `job-listings-${layout}`);
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
	buttons.forEach(button => {
		if (button.dataset.layout === activeLayout) {
			button.classList.add('active');
		} else {
			button.classList.remove('active');
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
	if (typeof localStorage !== 'undefined') {
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
	if (typeof localStorage !== 'undefined') {
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
	const filterButton = container.querySelector('.filter-button');
	const categorySelect = container.querySelector('.category-select');
	const locationSelect = container.querySelector('.location-select');
	const skillsSelect = container.querySelector('.skills-select');
	const searchInput = container.querySelector('.search-input');

	if (!filterButton) {
		return;
	}

	// Get current URL params
	const urlParams = new URLSearchParams(window.location.search);
	
	// Set initial values from URL if they exist
	if (searchInput && urlParams.has('search')) {
		searchInput.value = urlParams.get('search');
	}
	
	if (categorySelect && urlParams.has('category')) {
		categorySelect.value = urlParams.get('category');
	}
	
	if (locationSelect && urlParams.has('location')) {
		locationSelect.value = urlParams.get('location');
	}
	
	if (skillsSelect && urlParams.has('skills')) {
		skillsSelect.value = urlParams.get('skills');
	}

	// Handle filter button click
	filterButton.addEventListener('click', () => {
		// Get filter values
		const category = categorySelect ? categorySelect.value : '';
		const location = locationSelect ? locationSelect.value : '';
		const skills = skillsSelect ? skillsSelect.value : '';
		const search = searchInput ? searchInput.value : '';

		// Here you would typically make an AJAX request to filter jobs
		// For now, we just reload with query parameters
		const url = new URL(window.location.href);
		
		if (category) {
			url.searchParams.set('category', category);
		} else {
			url.searchParams.delete('category');
		}
		
		if (location) {
			url.searchParams.set('location', location);
		} else {
			url.searchParams.delete('location');
		}
		
		if (skills) {
			url.searchParams.set('skills', skills);
		} else {
			url.searchParams.delete('skills');
		}
		
		if (search) {
			url.searchParams.set('search', search);
		} else {
			url.searchParams.delete('search');
		}
		
		window.location.href = url.toString();
	});
}

/**
 * Setup the save job functionality
 */
function setupSaveJobButtons() {
	const saveButtons = document.querySelectorAll('.save-job-button');
	
	saveButtons.forEach(button => {
		button.addEventListener('click', (e) => {
			e.preventDefault();
			
			const jobId = button.dataset.jobId;
			
			if (!jobId) {
				return;
			}
			
			// Toggle saved state visually
			button.classList.toggle('saved');
			
			// TODO: Implement saving jobs to user preferences
			// This would typically involve an AJAX call to save the job
			console.log('Toggle saved state for job ID:', jobId);
		});
	});
}

/**
 * Highlight premium jobs with crown icon
 */
document.addEventListener('DOMContentLoaded', function() {
	// Add premium job indicators
	const premiumJobs = document.querySelectorAll('.job-card.premium, .job-row.premium');
	
	premiumJobs.forEach(job => {
		const indicator = document.createElement('div');
		indicator.className = 'premium-indicator';
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
document.addEventListener('DOMContentLoaded', function() {
	const saveButtons = document.querySelectorAll('.save-job-button');
	
	saveButtons.forEach(button => {
		button.addEventListener('click', function(e) {
			e.preventDefault();
			
			// Toggle filled/unfilled state
			const svg = button.querySelector('svg');
			
			if (svg.getAttribute('fill') === 'none') {
				svg.setAttribute('fill', 'currentColor');
				button.setAttribute('title', 'Saved');
				
				// You would add the job ID to saved jobs in user preferences
				// This is a simplified example
			} else {
				svg.setAttribute('fill', 'none');
				button.setAttribute('title', 'Save job');
				
				// You would remove the job ID from saved jobs in user preferences
			}
		});
	});
});
