# Update the JavaScript code to adjust the TAG column layout
// Import required modules
import $ from 'jquery';

// Adjust the TAG column layout
$(document).ready(function() {
    $('.tags-column').each(function() {
        var tags = $(this).find('.tag');
        var tagWidth = tags.outerWidth(true);
        if (tagWidth < 20) {
            tags.css('display', 'inline-block');
        }
    });
});