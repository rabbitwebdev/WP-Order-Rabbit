// jQuery(document).ready(function($) {
//     $('#stripe-checkout').on('click', function() {
//         const paymentIntentId = $(this).data('payment-intent');
        
//         // Call your server-side code to complete the payment
//         // For simplicity, let's assume you have an endpoint to complete the payment
//     });
// });
var stripe = Stripe("<?php echo STRIPE_TEST_PUBLISHABLE_KEY; ?>");
var elements = stripe.elements();
var cardElement = elements.create("card");
cardElement.mount("#card-element");

var checkoutButton = document.getElementById("stripe-checkout");

checkoutButton.addEventListener("click", function (event) {
    event.preventDefault();

    var paymentIntentId = checkoutButton.getAttribute("data-payment-intent");

    // Fetch the client secret for this payment intent
    fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            action: "wpor_create_payment_intent",
            payment_intent_id: paymentIntentId
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            var clientSecret = data.data.client_secret; // Extract the client_secret from the response

            // Confirm the payment using the client secret
            stripe.confirmCardPayment(clientSecret, {
                payment_method: {
                    card: cardElement
                }
            }).then(function(result) {
                if (result.error) {
                    console.log(result.error.message); // Handle error
                } else {
                    window.location.href = "<?php echo site_url('/thank-you'); ?>"; // Redirect on success
                }
            });
        } else {
            console.log('Error retrieving client secret:', data.message);
        }
    });
});


