jQuery(document).ready(function ($) {
  $(".trustist-payment-button").click(function () {
    // Disable the button and show a spinner
    $(this).prop("disabled", true);
    // Add a spinner here as needed

    // Retrieve the price from the data attribute
    var price = $(this).data("price");
    var returnUrl = $(this).data("return-url");

    // AJAX request to server
    $.ajax({
      url: trustistPluginAjax.ajaxurl,
      type: "POST",
      data: {
        action: "process_payment",
        price,
        returnUrl,
      },
      success: function (response) {
        window.location.href = response.data.paylink;

        // Remove spinner and enable button
      },
      error: function () {
        // Handle errors
        // Remove spinner and enable button
      },
    });
  });
});