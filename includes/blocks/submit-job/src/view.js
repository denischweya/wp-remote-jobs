/**
 * Use this file for JavaScript code that you want to run in the front-end
 * on posts/pages that contain this block.
 *
 * When this file is defined as the value of the `viewScript` property
 * in `block.json` it will be enqueued on the front end of the site.
 *
 * Example:
 *
 * ```js
 * {
 *   "viewScript": "file:./view.js"
 * }
 * ```
 *
 * If you're not making any changes to this file because your project doesn't need any
 * JavaScript running in the front-end, then you should delete this file and remove
 * the `viewScript` property from `block.json`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
 */

/* eslint-disable no-console */
console.log( 'Hello World! (from create-block-submit-job block)' );
/* eslint-enable no-console */


jQuery(document).ready(function($) {
    $('.select-skills').select2({
        tags: true,
        tokenSeparators: [','],
        placeholder: 'Select or type skills',
        allowClear: true
    });
    $('.select-tags').select2({
        tags: true,
        tokenSeparators: [','],
        placeholder: 'Select or type tags',
        allowClear: true
    });
    $('.select-benefits').select2({
        placeholder: "Select Benefits",
        allowClear: true
    });

    $('.select-country').select2({
        placeholder: "Select Skills",
        allowClear: true
    });
    $('input[name="worldwide"]').on('change', function() {
        $('select[name="job_location"]').toggle(this.value === 'no');
    });
});

jQuery(document).ready(function($) {
    $('.next-step').click(function() {
        $('#step1').hide();
        $('#step2').show();
    });

    $('.prev-step').click(function() {
        $('#step2').hide();
        $('#step1').show();
    });
});

if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Initialize Select2
jQuery(document).ready(function($) {
    $('.select2-single').select2();
    
    // Handle worldwide radio button changes
    $('input[name="worldwide"]').change(function() {
        if ($(this).val() === 'no') {
            $('.location-select').show();
            $('#job_location').prop('required', true);
        } else {
            $('.location-select').hide();
            $('#job_location').prop('required', false);
        }
    });
});
