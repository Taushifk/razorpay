<?php

namespace Taushifk\Razorpay\Concerns;

use InvalidArgumentException;
use Taushifk\Razorpay\Cashier;
use LogicException;

trait PerformsCharges
{
    /**
     * Generate a pay link for a "one off" charge on the customer for the given amount.
     *
     * @param  int  $amount
     * @param  string  $title
     * @param  array  $options
     * @return string
     *
     * @throws \Exception
     */
    public function charge(int $amount, string $title, array $options = [])
    {
        return Cashier::Razorpay()->invoice->create(array_merge([
            //'type' => 'invoice',
            'line_items' => [
                [
                    'name' => $title,
                    'amount' => (int) $amount * 100,
                    'quantity' => 1
                ]
            ],
            'customer' => [
                'name' => $this->name,
                'email' => $this->email,
            ],
            //'currency' => config('cashier.currency'),
            'email_notify' => 1,
        ], $options))['short_url'];
    }

    /**
     * Generate a new pay link.
     *
     * @param  array  $payload
     * @return string
     */
    protected function generatePayLink(array $payload)
    {
        // We'll need a way to identify the user in any webhook we're catching so before
        // we make the API request we'll attach the authentication identifier to this
        // payload so we can match it back to a user when handling Paddle webhooks.
        if (! isset($payload['notes'])) {
            $payload['notes'] = [];
        }

        if (! is_array($payload['notes'])) {
            throw new LogicException('The value for "notes" always needs to be an array.');
        }

        $payload['notes']['billable_id'] = $this->getKey();
        $payload['notes']['billable_type'] = $this->getMorphClass();

        $payload = array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $payload);

        return Cashier::Razorpay()->subscription->create($payload)['short_url'];
    }

    /**
     * Refund a given order.
     *
     * @param  int  $orderId
     * @param  float|null  $amount
     * @param  string  $reason
     * @return int
     */
    public function refund($paymentId, $amount = null, $reason = '')
    {
        $payment = Cashier::Razorpay()->payment->fetch($paymentId);
        
        if ($amount) {
            $payload['amount'] = $amount * 100;
        }
        
        $payload['notes'] = ['reason' => $reason];
        
        return $payment->refund($payload)['id'];
    }
}
