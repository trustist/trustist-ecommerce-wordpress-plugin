function SaveNotifications() {
    var notifications = [];
    jQuery('.notification_checkbox').each(function() {
        if (jQuery(this).is(':checked')) {
            notifications.push(jQuery(this).val());
        }
    });
    jQuery('#selectedNotifications').val(jQuery.toJSON(notifications));
}

function ToggleNotifications() {

    var container = jQuery('#gf_trustist_notification_container');
    var isChecked = jQuery('#delaynotification').is(':checked');

    if (isChecked) {
        container.slideDown();
        jQuery('.gf_trustist_notification input').prop('checked', true);
    } else {
        container.slideUp();
        jQuery('.gf_trustist_notification input').prop('checked', false);
    }

    SaveNotifications();
}

jQuery(document).ready(function() {
    jQuery('#gf_trustist_custom_settings label.left_header').css('margin-left', '-200px');
});