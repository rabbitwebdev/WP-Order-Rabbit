<?php
class WPOR_Stripe {
    private $stripe;

    public function __construct() {
        \Stripe\Stripe::setApiKey('sk_test_51D6ONeIF6Cy7jqRet27KpvHQKXN0jEKPm6tj2OUZhgjhbxKQY7QekwB3kothe2oVCRsvlOcFJme7AIBVqfwDuGVA00yBMG4twd');
        $this->stripe = new \Stripe\StripeClient('sk_test_51D6ONeIF6Cy7jqRet27KpvHQKXN0jEKPm6tj2OUZhgjhbxKQY7QekwB3kothe2oVCRsvlOcFJme7AIBVqfwDuGVA00yBMG4twd');
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
