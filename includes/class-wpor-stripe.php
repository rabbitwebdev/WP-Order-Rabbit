<?php
class WPOR_Stripe {
    private $stripe;

    public function __construct() {
        \Stripe\Stripe::setApiKey('your-stripe-secret-key');
        $this->stripe = new \Stripe\StripeClient('your-stripe-secret-key');
    }

    public function create_payment_intent($amount) {
        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $amount * 100, // Amount in cents
                'currency' => 'usd',
                'automatic_payment_methods' => ['enabled' => true],
            ]);
            return $paymentIntent;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return false;
        }
    }
}
