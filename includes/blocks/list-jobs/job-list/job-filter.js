document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.job-search-form');
    const jobListings = document.getElementById('job-listings');
    const searchInput = document.getElementById('job-search');
    const categorySelect = document.getElementById('job-category');
    const typeSelect = document.getElementById('job-type');
    const locationSelect = document.getElementById('job-location');

    function filterJobs() {
        const formData = new FormData();
        formData.append('action', 'filter_jobs');
        formData.append('search', searchInput.value);
        formData.append('category', categorySelect.value);
        formData.append('type', typeSelect.value);
        formData.append('location', locationSelect.value);

        fetch(form.dataset.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                jobListings.innerHTML = data.data;
            } else {
                console.error('Error filtering jobs:', data.data);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    searchInput.addEventListener('input', filterJobs);
    categorySelect.addEventListener('change', filterJobs);
    typeSelect.addEventListener('change', filterJobs);
    locationSelect.addEventListener('change', filterJobs);
});
