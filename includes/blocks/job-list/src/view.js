document.addEventListener( 'DOMContentLoaded', function () {
	const form = document.querySelector( '.job-search-form' );
	const jobListings = document.getElementById( 'job-listings' );
	const searchInput = document.getElementById( 'job-search' );
	const categorySelect = document.getElementById( 'job-category' );
	const typeSelect = document.getElementById( 'job-type' );
	const locationSelect = document.getElementById( 'job-location' );

	function filterJobs() {
		const formData = new FormData();
		formData.append( 'action', 'filter_jobs' );
		formData.append( 'search', searchInput.value );
		formData.append( 'category', categorySelect.value );
		formData.append( 'type', typeSelect.value );
		formData.append( 'location', locationSelect.value );

		fetch( form.getAttribute( 'data-ajax-url' ), {
			method: 'POST',
			body: formData,
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				if ( data.success ) {
					jobListings.innerHTML = data.data;
				} else {
					console.error( 'Error:', data );
				}
			} )
			.catch( ( error ) => console.error( 'Error:', error ) );
	}

	searchInput.addEventListener( 'input', filterJobs );
	categorySelect.addEventListener( 'change', filterJobs );
	typeSelect.addEventListener( 'change', filterJobs );
	locationSelect.addEventListener( 'change', filterJobs );
} );

/**
 * Job Listings layout toggle and filtering functionality
 */
document.addEventListener('DOMContentLoaded', function() {
	// Layout toggle functionality
	const gridButton = document.querySelector('.toggle-grid');
	const listButton = document.querySelector('.toggle-list');
	
	if (gridButton && listButton) {
		const jobListingsContainer = document.querySelector('.job-listings-grid, .job-listings-list');
		
		if (jobListingsContainer) {
			// Set initial active state based on current class
			if (jobListingsContainer.classList.contains('job-listings-grid')) {
				gridButton.classList.add('active');
				listButton.classList.remove('active');
			} else if (jobListingsContainer.classList.contains('job-listings-list')) {
				listButton.classList.add('active');
				gridButton.classList.remove('active');
			}
			
			// Grid button click handler
			gridButton.addEventListener('click', function() {
				jobListingsContainer.classList.remove('job-listings-list');
				jobListingsContainer.classList.add('job-listings-grid');
				gridButton.classList.add('active');
				listButton.classList.remove('active');
				
				// Save preference in localStorage
				localStorage.setItem('remjobs_layout_preference', 'grid');
				
				// Update URL with layout parameter
				updateUrlParameter('layout', 'grid');
			});
			
			// List button click handler
			listButton.addEventListener('click', function() {
				jobListingsContainer.classList.remove('job-listings-grid');
				jobListingsContainer.classList.add('job-listings-list');
				listButton.classList.add('active');
				gridButton.classList.remove('active');
				
				// Save preference in localStorage
				localStorage.setItem('remjobs_layout_preference', 'list');
				
				// Update URL with layout parameter
				updateUrlParameter('layout', 'list');
			});
			
			// Apply saved preference from localStorage if it exists
			const savedPreference = localStorage.getItem('remjobs_layout_preference');
			if (savedPreference === 'grid') {
				jobListingsContainer.classList.remove('job-listings-list');
				jobListingsContainer.classList.add('job-listings-grid');
				gridButton.classList.add('active');
				listButton.classList.remove('active');
			} else if (savedPreference === 'list') {
				jobListingsContainer.classList.remove('job-listings-grid');
				jobListingsContainer.classList.add('job-listings-list');
				listButton.classList.add('active');
				gridButton.classList.remove('active');
			}
		}
	}
	
	// Helper function to update URL parameters without page refresh
	function updateUrlParameter(key, value) {
		const url = new URL(window.location.href);
		url.searchParams.set(key, value);
		window.history.replaceState({}, '', url);
	}
});

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
