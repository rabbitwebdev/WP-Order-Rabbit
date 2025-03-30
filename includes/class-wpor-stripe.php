<?php
class WPOR_Stripe {

    public function __construct() {
        \Stripe\Stripe::setApiKey(STRIPE_TEST_SECRET_KEY);
    }

    public function create_payment_intent($total_price) {
        try {
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $total_price * 100, // Stripe expects the amount in cents
                'currency' => 'usd',
            ]);
            return $payment_intent;
        } catch (Exception $e) {
            return false;
        }
    }
}

