/**
 * Admin-specific functionality for the Remote Jobs plugin
 *
 * @package    Remote_Jobs
 * @subpackage Remote_Jobs/admin/js
 * @since      1.0.0
 */

(function($) {
    'use strict';

    // Document ready handler
    $(function() {
        // Initialize any admin UI components
        initializeAdminUI();
    });

    /**
     * Initialize admin UI components
     */
    function initializeAdminUI() {
        // Initialize select2 for job categories
        if ($.fn.select2) {
            $('.remjobs-select2').select2({
                width: '100%',
                placeholder: $(this).data('placeholder')
            });
        }

        // Handle form submissions
        $('#remjobs-admin-form').on('submit', function(e) {
            e.preventDefault();
            // Add form submission logic here
        });
    }

})(jQuery);