<?php

namespace Taushifk\Razorpay\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Taushifk\Razorpay\Cashier;
use Taushifk\Razorpay\Events\PaymentSucceeded;
use Taushifk\Razorpay\Events\SubscriptionCancelled;
use Taushifk\Razorpay\Events\SubscriptionCreated;
use Taushifk\Razorpay\Events\SubscriptionPaymentFailed;
use Taushifk\Razorpay\Events\SubscriptionPaymentSucceeded;
use Taushifk\Razorpay\Events\SubscriptionUpdated;
use Taushifk\Razorpay\Events\WebhookHandled;
use Taushifk\Razorpay\Events\WebhookReceived;
//use Taushifk\Razorpay\Exceptions\InvalidPassthroughPayload;
use Taushifk\Razorpay\Http\Middleware\VerifyWebhookSignature;
use Taushifk\Razorpay\Subscription;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Create a new WebhookController instance.
     *
     * @return void
     */
    public function __construct()
    {
        if (config('cashier.key')) {
            $this->middleware(VerifyWebhookSignature::class);
        }
    }

    /**
     * Handle a Paddle webhook call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function __invoke(Request $request)
    {
        $payload = $request->all();

        if (! isset($payload['event'])) {
            return new Response();
        }

        $method = 'handle'.Str::of($payload['event'])->replace('.', '_')->studly();

        WebhookReceived::dispatch($payload);

        if (method_exists($this, $method)) {
            try {
                $this->{$method}($payload);
            } catch (\Exception $e) {
                return new Response('Webhook Skipped');
            }

            WebhookHandled::dispatch($payload);

            return new Response('Webhook Handled');
        }

        return new Response();
    }

    /**
     * Handle one-time payment succeeded.
     *
     * @param  array  $payload
     * @return void
     */
    protected function handleInvoicePaid(array $payload)
    {
        if ($this->receiptExists($payload['payload']['invoice']['entity']['order_id'])) {
            return;
        }
        
        $invoice = $payload['payload']['invoice']['entity'];
        
        //check is invoice for subscription
        if(!isset($invoice['subscription_id']))
        {
            return;
        }
        
        $subscription = Cashier::$subscriptionModel::where('razorpay_id', $invoice['subscription_id'])->latest()->first();
        
        if(!$subscription) return;
        
        $customer = $subscription->billable;

        $receipt = $customer->receipts()->create([
            'payment_id' => $invoice['payment_id'],
            'order_id' => $invoice['order_id'],
            'razorpay_subscription_id' => $invoice['subscription_id'],
            'amount' => $invoice['amount'] / 100,
            'tax' => $invoice['tax_amount'] / 100,
            'currency' => $invoice['currency'],
            'quantity' => isset($payload['quantity']) ? $payload['quantity'] : 1,
            'receipt_url' => $invoice['short_url'],
            'paid_at' => Carbon::parse($invoice['paid_at'])->timezone('UTC'),
        ]);

        PaymentSucceeded::dispatch($customer, $receipt, $payload);
    }

    /**
     * Handle subscription payment succeeded.
     *
     * @param  array  $payload
     * @return void
     */
    protected function handleSubscriptionCharged(array $payload)
    {
        //SubscriptionPaymentSucceeded::dispatch($billable, $receipt, $payload);
    }

    /**
     * Handle subscription payment failed.
     *
     * @param  array  $payload
     * @return void
     */
    protected function handleSubscriptionPaymentFailed(array $payload)
    {
        if ($subscription = $this->findSubscription($payload['subscription_id'])) {
            SubscriptionPaymentFailed::dispatch($subscription->billable, $payload);
        }
    }

    /**
     * Handle subscription created.
     *
     * @param  array  $payload
     * @return void
     *
     * @throws \Laravel\Paddle\Exceptions\InvalidPassthroughPayload
     */
    protected function handleSubscriptionAuthenticated(array $payload)
    {
        $subscriptionPayload = $payload['payload']['subscription']['entity'];
        $passthrough = $subscriptionPayload['notes'];

        if (! is_array($passthrough)) {
            throw new \Expception('InvalidPassthroughPayload');
        }

        $customer = $this->findOrCreateCustomer($passthrough);
        
        $startAt = Carbon::parse($subscriptionPayload['start_at']);

        $trialEndsAt = $startAt->isFuture()
            ? $startAt->timezone('UTC')->startOfDay()
            : null;

        $subscription = $customer->subscriptions()->create([
            'name' => $passthrough['subscription_name'] ?? 'default',
            'razorpay_id' => $subscriptionPayload['id'],
            'razorpay_plan' => $subscriptionPayload['plan_id'],
            'razorpay_status' => $subscriptionPayload['status'],
            'quantity' => $subscriptionPayload['quantity'],
            'trial_ends_at' => $trialEndsAt,
        ]);

        SubscriptionCreated::dispatch($customer, $subscription, $payload);
    }

    /**
     * Handle subscription updated.
     *
     * @param  array  $payload
     * @return void
     */
    protected function handleSubscriptionUpdated(array $payload)
    {
        $subscriptionPayload = $payload['payload']['subscription']['entity'];
        
        if (! $subscription = $this->findSubscription($subscriptionPayload['id'])) {
            return;
        }

        // Plan...
        if (isset($subscriptionPayload['plan_id'])) {
            $subscription->razorpay_plan = $subscriptionPayload['plan_id'];
        }

        // Status...
        if (isset($subscriptionPayload['status'])) {
            $subscription->razorpay_status = $subscriptionPayload['status'];
        }

        // Quantity...
        if (isset($subscriptionPayload['quantity'])) {
            $subscription->quantity = $subscriptionPayload['quantity'];
        }
        
        $subscription->save();

        SubscriptionUpdated::dispatch($subscription, $payload);
    }
    
    protected function handleSubscriptionPaused(array $payload)
    {
        $subscriptionPayload = $payload['payload']['subscription']['entity'];
        
        if (! $subscription = $this->findSubscription($subscriptionPayload['id'])) {
            return;
        }
        
        // Status...
        if (isset($subscriptionPayload['status'])) {
            $subscription->razorpay_status = $subscriptionPayload['status'];
        }
        
        // Paused...
        $subscription->ends_at = Carbon::parse($subscriptionPayload['current_end'])->timezone('UTC');
        
        $subscription->paused_from = now();

        $subscription->save();

        SubscriptionUpdated::dispatch($subscription, $payload);
    }
    
    protected function handleSubscriptionResumed(array $payload)
    {
        $subscriptionPayload = $payload['payload']['subscription']['entity'];
        
        if (! $subscription = $this->findSubscription($subscriptionPayload['id'])) {
            return;
        }
        
        // Status...
        if (isset($subscriptionPayload['status'])) {
            $subscription->razorpay_status = $subscriptionPayload['status'];
        }
        
        // Paused...
        $subscription->paused_from = null;
        $subscription->ends_at = null;
        
        $subscription->save();

        //SubscriptionUpdated::dispatch($subscription, $payload);
    }
    
    protected function handleSubscriptionHalted(array $payload)
    {
        $subscriptionPayload = $payload['payload']['subscription']['entity'];
        
        if (! $subscription = $this->findSubscription($subscriptionPayload['id'])) {
            return;
        }
        
        // Status...
        if (isset($subscriptionPayload['status'])) {
            $subscription->razorpay_status = $subscriptionPayload['status'];
        }
        
        $subscription->ends_at = now();

        $subscription->save();

        SubscriptionUpdated::dispatch($subscription, $payload);
    }

    /**
     * Handle subscription cancelled.
     *
     * @param  array  $payload
     * @return void
     */
    protected function handleSubscriptionCancelled(array $payload)
    {
        $subscriptionPayload = $payload['payload']['subscription']['entity'];
        
        if (! $subscription = $this->findSubscription($subscriptionPayload['id'])) {
            return;
        }

        // Cancellation date...
        if (is_null($subscription->ends_at)) {
            $subscription->ends_at = Carbon::parse($subscriptionPayload['current_end'])->timezone('UTC');
        }

        // Status...
        if (isset($subscriptionPayload['status'])) {
            $subscription->razorpay_status = $subscriptionPayload['status'];
        }

        $subscription->paused_from = null;

        $subscription->save();

        SubscriptionCancelled::dispatch($subscription, $payload);
    }
    
    protected function handleSubscriptionCompleted(array $payload)
    {
        $subscriptionPayload = $payload['payload']['subscription']['entity'];
        
        if (! $subscription = $this->findSubscription($subscription['id'])) {
            return;
        }

        // Cancellation date...
        $subscription->ends_at = Carbon::parse($subscriptionPayload['current_end'])->timezone('UTC');

        // Status...
        $subscription->razorpay_status = $subscriptionPayload['status'];

        $subscription->paused_from = null;

        $subscription->save();
    }

    /**
     * Find or create a customer based on the passthrough values and return the billable model.
     *
     * @param  string  $passthrough
     * @return \Laravel\Paddle\Billable
     *
     * @throws \Laravel\Paddle\Exceptions\InvalidPassthroughPayload
     */
    protected function findOrCreateCustomer(array $passthrough)
    {
        //$passthrough = json_decode($passthrough, true);

        if (! is_array($passthrough) || ! isset($passthrough['billable_id'])) {
            throw new \Exception('InvalidPassthroughPayload');
        }

        return Cashier::$customerModel::where([
            'id' => $passthrough['billable_id'] ,
        ])->first();
    }

    /**
     * Find the first subscription matching a Paddle subscription id.
     *
     * @param  string  $subscriptionId
     * @return \Laravel\Paddle\Subscription|null
     */
    protected function findSubscription(string $subscriptionId)
    {
        return Cashier::$subscriptionModel::firstWhere('razorpay_id', $subscriptionId);
    }

    /**
     * Determine if a receipt with a given Order ID already exists.
     *
     * @param  string  $orderId
     * @return bool
     */
    protected function receiptExists(string $orderId)
    {
        return Cashier::$receiptModel::where('order_id', $orderId)->count() > 0;
    }
}
