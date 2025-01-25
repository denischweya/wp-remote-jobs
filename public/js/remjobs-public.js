/**
 * Public-facing functionality for the Remote Jobs plugin
 *
 * @package    Remote_Jobs
 * @subpackage Remote_Jobs/public/js
 * @since      1.0.0
 */

(function($) {
    'use strict';

    // Document ready handler
    $(function() {
        initializePublicUI();
        handleJobSubmission();
    });

    /**
     * Initialize public UI components
     */
    function initializePublicUI() {
        // Initialize select2 for job fields
        if ($.fn.select2) {
            $('.remjobs-select2').select2({
                width: '100%',
                placeholder: $(this).data('placeholder')
            });
        }

        // Initialize job search filters
        $('.remjobs-filter').on('change', function() {
            // Add job filtering logic here
        });
    }

    /**
     * Handle job submission form
     */
    function handleJobSubmission() {
        $('#remjobs-submit-form').on('submit', function(e) {
            e.preventDefault();
            // Add job submission logic here
        });
    }

})(jQuery);