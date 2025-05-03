<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);



function approveSession($paymentIntentId) {
    try {
        // Confirm the payment intent (capture the payment)
        $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
        $paymentIntent->capture();

        return [
            'status' => 'success',
            'message' => 'Session approved and payment captured'
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        // Handle error
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

function handlePaymentSplit($paymentIntentId, $tutorAccountId, $amount) {
    try {
        // Transfer 90% to the tutor's connected account
        $transfer = \Stripe\Transfer::create([
            'amount' => (int)($amount * 0.9), // 90% to tutor
            'currency' => 'cad',
            'destination' => $tutorAccountId,
            'transfer_group' => $paymentIntentId,
        ]);

        return [
            'status' => 'success',
            'message' => 'Payment split and 90% transferred to tutor'
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        // Handle error
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

function createStripeAccountForTutor($tutorEmail) {
    try {
        $account = \Stripe\Account::create([
            'type' => 'express',
            'country' => 'CA', 
            'email' => $tutorEmail,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ]
        ]);

        $accountLink = \Stripe\AccountLink::create([
            'account' => $account->id,
            'refresh_url' => 'http://localhost/project1/my_sessions.php',
            'return_url' => 'http://localhost/project1/my_sessions.php?stripe_account_id=' . $account->id,
            'type' => 'account_onboarding',
        ]);

        return [
            'status' => 'success',
            'stripe_account_id' => $account->id,
            'accountLinkUrl' => $accountLink->url
        ];

    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe Account Creation Error: ' . $e->getMessage());
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

function refundUncapturedPayment($paymentIntentId) {
    try {
        // Retrieve the payment intent details
        $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
        
        
        try {
            $canceledIntent = $paymentIntent->cancel();
            
            return [
                'status' => 'success',
                'message' => 'Payment intent canceled successfully'
            ];
        } catch (\Exception $cancelException) {
            return [
                'status' => 'error',
                'message' => 'Could not cancel payment: ' . $cancelException->getMessage()
            ];
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

function refundCapturedPayment($paymentIntentId) {
    try {
        // Retrieve the payment intent details
        $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
        
        if ($paymentIntent->status === 'succeeded') {
            // Issue full refund for captured payments
            \Stripe\Refund::create([
                'payment_intent' => $paymentIntentId
            ]);
            return [
                'status' => 'success',
                'message' => 'Full refund issued for captured payment'
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Payment intent has not been captured yet'
            ];
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}





?>
