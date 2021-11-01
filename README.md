# Laravel Cashier (razorpay)

- [Introduction](#introduction)
- [Upgrading Cashier](#upgrading-cashier)
- [Installation](#installation)
    - [Razorpay Sandbox](#Razorpay-sandbox)
    - [Database Migrations](#database-migrations)
- [Configuration](#configuration)
    - [Billable Model](#billable-model)
    - [API Keys](#api-keys)
    - [Razorpay JS](#Razorpay-js)
    - [Currency Configuration](#currency-configuration)
    - [Overriding Default Models](#overriding-default-models)
- [Core Concepts](#core-concepts)
    - [Pay Links](#pay-links)
    - [Inline Checkout](#inline-checkout)
    - [User Identification](#user-identification)
- [Prices](#prices)
- [Customers](#customers)
    - [Customer Defaults](#customer-defaults)
- [Subscriptions](#subscriptions)
    - [Creating Subscriptions](#creating-subscriptions)
    - [Checking Subscription Status](#checking-subscription-status)
    - [Subscription Single Charges](#subscription-single-charges)
    - [Updating Payment Information](#updating-payment-information)
    - [Changing Plans](#changing-plans)
    - [Subscription Quantity](#subscription-quantity)
    - [Subscription Modifiers](#subscription-modifiers)
    - [Pausing Subscriptions](#pausing-subscriptions)
    - [Cancelling Subscriptions](#cancelling-subscriptions)
- [Subscription Trials](#subscription-trials)
    - [With Payment Method Up Front](#with-payment-method-up-front)
    - [Without Payment Method Up Front](#without-payment-method-up-front)
- [Handling Razorpay Webhooks](#handling-Razorpay-webhooks)
    - [Defining Webhook Event Handlers](#defining-webhook-event-handlers)
    - [Verifying Webhook Signatures](#verifying-webhook-signatures)
- [Single Charges](#single-charges)
    - [Simple Charge](#simple-charge)
    - [Charging Products](#charging-products)
    - [Refunding Orders](#refunding-orders)
- [Receipts](#receipts)
    - [Past & Upcoming Payments](#past-and-upcoming-payments)
- [Handling Failed Payments](#handling-failed-payments)
- [Testing](#testing)

<a name="introduction"></a>
## Introduction

Laravel Cashier razorpay provides an expressive, fluent interface to [razorpay's](https://razorpay.com) subscription billing services. It handles almost all of the boilerplate subscription billing code you are dreading. In addition to basic subscription management, Cashier can handle: coupons, swapping subscription, subscription "quantities", cancellation grace periods, and more.

While working with Cashier we recommend you also review Razorpay's [user guides](https://developer.Razorpay.com/guides) and [API documentation](https://developer.Razorpay.com/api-reference/intro).

<a name="upgrading-cashier"></a>
## Upgrading Cashier

<a name="installation"></a>
## Installation

First, install the Cashier package for Razorpay using the Composer package manager:

    composer require Taushifk/cashier-Razorpay

> {note} To ensure Cashier properly handles all Razorpay events, remember to [set up Cashier's webhook handling](#handling-Razorpay-webhooks).

<a name="Razorpay-sandbox"></a>
### Razorpay Sandbox

After you have finished developing your application you may [apply for a Razorpay vendor account](https://Razorpay.com).

<a name="database-migrations"></a>
### Database Migrations

The Cashier service provider registers its own database migration directory, so remember to migrate your database after installing the package. The Cashier migrations will create a new `customers` table. In addition, a new `subscriptions` table will be created to store all of your customer's subscriptions. Finally, a new `receipts` table will be created to store all of your application's receipt information:

    php artisan migrate

If you need to overwrite the migrations that are included with Cashier, you can publish them using the `vendor:publish` Artisan command:

    php artisan vendor:publish --tag="cashier-migrations"

If you would like to prevent Cashier's migrations from running entirely, you may use the `ignoreMigrations` provided by Cashier. Typically, this method should be called in the `register` method of your `AppServiceProvider`:

    use Taushifk\Razorpay\Cashier;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Cashier::ignoreMigrations();
    }

<a name="configuration"></a>
## Configuration

<a name="billable-model"></a>
### Billable Model

Before using Cashier, you must add the `Billable` trait to your user model definition. This trait provides various methods to allow you to perform common billing tasks, such as creating subscriptions, applying coupons and updating payment method information:

    use Taushifk\Razorpay\Billable;

    class User extends Authenticatable
    {
        use Billable;
    }

<a name="api-keys"></a>
### API Keys

Next, you should configure your Razorpay keys in your application's `.env` file. You can retrieve your Razorpay API keys from the Razorpay control panel:

    RAZORPAY_KEY=your-Razorpay-vendor-id
    RAZORPAY_SECRET=your-Razorpay-vendor-auth-code

<a name="Razorpay-js"></a>
### Razorpay JS

Razorpay relies on its own JavaScript library to initiate the Razorpay checkout widget. You can load the JavaScript library by placing the `@RazorpayJS` Blade directive right before your application layout's closing `</head>` tag:

    <head>
        ...

        @RazorpayJS
    </head>

<a name="currency-configuration"></a>
### Currency Configuration

The default Cashier currency is India Rupees (INR). You can change the default currency by defining a `CASHIER_CURRENCY` environment variable within your application's `.env` file:

    CASHIER_CURRENCY=EUR

In addition to configuring Cashier's currency, you may also specify a locale to be used when formatting money values for display on invoices. Internally, Cashier utilizes [PHP's `NumberFormatter` class](https://www.php.net/manual/en/class.numberformatter.php) to set the currency locale:

    CASHIER_CURRENCY_LOCALE=nl_BE

> {note} In order to use locales other than `en`, ensure the `ext-intl` PHP extension is installed and configured on your server.

<a name="overriding-default-models"></a>
### Overriding Default Models

You are free to extend the models used internally by Cashier by defining your own model and extending the corresponding Cashier model:

    use Taushifk\Razorpay\Subscription as CashierSubscription;

    class Subscription extends CashierSubscription
    {
        // ...
    }

After defining your model, you may instruct Cashier to use your custom model via the `Taushifk\Razorpay\Cashier` class. Typically, you should inform Cashier about your custom models in the `boot` method of your application's `App\Providers\AppServiceProvider` class:

    use App\Models\Cashier\Receipt;
    use App\Models\Cashier\Subscription;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Cashier::useReceiptModel(Receipt::class);
        Cashier::useSubscriptionModel(Subscription::class);
    }

<a name="core-concepts"></a>
## Core Concepts

<a name="pay-links"></a>
### Pay Links

Razorpay lacks an extensive CRUD API to perform subscription state changes. Therefore, most interactions with Razorpay are done through its [checkout widget](https://developer.Razorpay.com/guides/how-tos/checkout/Razorpay-checkout). Before we can display the checkout widget, we must generate a "pay link" using Cashier. A "pay link" will inform the checkout widget of the billing operation we wish to perform:

    use App\Models\User;
    use Illuminate\Http\Request;

    Route::get('/user/subscribe', function (Request $request) {
        $payLink = $request->user()->newSubscription('default', $premium = 34567)
            ->create();

        return view('billing', ['payLink' => $payLink]);
    });

Cashier includes a `Razorpay-button` [Blade component](/docs/{{version}}/blade#components). We may pass the pay link URL to this component as a "prop". When this button is clicked, Razorpay's checkout widget will be displayed:

```html
<x-Razorpay-button :url="$payLink" class="px-8 py-4">
    Subscribe
</x-Razorpay-button>
```

By default, this will display a button with the standard Razorpay styling. You can remove all Razorpay styling by adding the `data-theme="none"` attribute to the component:

```html
<x-Razorpay-button :url="$payLink" class="px-8 py-4" data-theme="none">
    Subscribe
</x-Razorpay-button>
```

The Razorpay checkout widget is asynchronous. Once the user creates or updates a subscription within the widget, Razorpay will send your application webhooks so that you may properly update the subscription state in our own database. Therefore, it's important that you properly [set up webhooks](#handling-Razorpay-webhooks) to accommodate for state changes from Razorpay.

For more information on pay links, you may review [the Razorpay API documentation on pay link generation](https://developer.Razorpay.com/api-reference/product-api/pay-links/createpaylink).

> {note} After a subscription state change, the delay for receiving the corresponding webhook is typically minimal but you should account for this in your application by considering that your user's subscription might not be immediately available after completing the checkout.

<a name="manually-rendering-pay-links"></a>
#### Manually Rendering Pay Links

You may also manually render a pay link without using Laravel's built-in Blade components. To get started, generate the pay link URL as demonstrated in previous examples:

    $payLink = $request->user()->newSubscription('default', $premium = 34567)
        ->create();

Next, simply attach the pay link URL to an `a` element in your HTML:

    <a href="#!" class="ml-4 Razorpay_button" data-subscription-id="{{ $payLink }}">
        Razorpay Checkout
    </a>

<a name="payments-requiring-additional-confirmation"></a>
#### Payments Requiring Additional Confirmation

Sometimes additional verification is required in order to confirm and process a payment. When this happens, Razorpay will present a payment confirmation screen. Payment confirmation screens presented by Razorpay or Cashier may be tailored to a specific bank or card issuer's payment flow and can include additional card confirmation, a temporary small charge, separate device authentication, or other forms of verification.

<a name="inline-checkout"></a>
### Inline Checkout

If you don't want to make use of Razorpay's "overlay" style checkout widget, Razorpay also provides the option to display the widget inline. While this approach does not allow you to adjust any of the checkout's HTML fields, it allows you to embed the widget within your application.

To make it easy for you to get started with inline checkout, Cashier includes a `Razorpay-checkout` Blade component. To get started, you should [generate a pay link](#pay-links) and pass the pay link to the component's `override` attribute:

```html
<x-Razorpay-checkout :override="$payLink" class="w-full" />
```

To adjust the height of the inline checkout component, you may pass the `height` attribute to the Blade component:

    <x-Razorpay-checkout :override="$payLink" class="w-full" height="500" />

<a name="inline-checkout-without-pay-links"></a>
#### Inline Checkout Without Pay Links

Alternatively, you may customize the widget with custom options instead of using a pay link:

    $options = [
        'product' => $productId,
        'title' => 'Product Title',
    ];

    <x-Razorpay-checkout :options="$options" class="w-full" />

Please consult Razorpay's [guide on Inline Checkout](https://developer.Razorpay.com/guides/how-tos/checkout/inline-checkout) as well as their [parameter reference](https://developer.Razorpay.com/reference/Razorpay-js/parameters) for further details on the inline checkout's available options.

> {note} If you would like to also use the `passthrough` option when specifying custom options, you should provide a key / value array as its value. Cashier will automatically handle converting the array to a JSON string. In addition, the `customer_id` passthrough option is reserved for internal Cashier usage.

<a name="manually-rendering-an-inline-checkout"></a>
#### Manually Rendering An Inline Checkout

You may also manually render an inline checkout without using Laravel's built-in Blade components. To get started, generate the pay link URL [as demonstrated in previous examples](#pay-links).

Next, you may use Razorpay.js to initialize the checkout. To keep this example simple, we will demonstrate this using [Alpine.js](https://github.com/alpinejs/alpine); however, you are free to translate this example to your own frontend stack:

```html
<div class="Razorpay-checkout" x-data="{}" x-init="
    Razorpay.Checkout.open({
        override: {{ $payLink }},
        method: 'inline',
        frameTarget: 'Razorpay-checkout',
        frameInitialHeight: 366,
        frameStyle: 'width: 100%; background-color: transparent; border: none;'
    });
">
</div>
```

<a name="user-identification"></a>
### User Identification

In contrast to Stripe, Razorpay users are unique across all of Razorpay, not unique per Razorpay account. Because of this, Razorpay's API's do not currently provide a method to update a user's details such as their email address. When generating pay links, Razorpay identifies users using the `customer_email` parameter. When creating a subscription, Razorpay will try to match the user provided email to an existing Razorpay user.

In light of this behavior, there are some important things to keep in mind when using Cashier and Razorpay. First, you should be aware that even though subscriptions in Cashier are tied to the same application user, **they could be tied to different users within Razorpay's internal systems**. Secondly, each subscription has its own connected payment method information and could also have different email addresses within Razorpay's internal systems (depending on which email was assigned to the user when the subscription was created).

Therefore, when displaying subscriptions you should always inform the user which email address or payment method information is connected to the subscription on a per-subscription basis. Retrieving this information can be done with the following methods provided by the `Taushifk\Razorpay\Subscription` model:

    $subscription = $user->subscription('default');

    $subscription->RazorpayEmail();
    $subscription->paymentMethod();
    $subscription->cardBrand();
    $subscription->cardLastFour();

There is currently no way to modify a user's email address through the Razorpay API. When a user wants to update their email address within Razorpay, the only way for them to do so is to contact Razorpay customer support. When communicating with Razorpay, they need to provide the `RazorpayEmail` value of the subscription to assist Razorpay in updating the correct user.

<a name="customers"></a>
## Customers

<a name="customer-defaults"></a>
### Customer Defaults

Cashier allows you to define some useful defaults for your customers when creating pay links. Setting these defaults allow you to pre-fill a customer's email address, country, and postal code so that they can immediately move on to the payment portion of the checkout widget. You can set these defaults by overriding the following methods on your billable model:

    /**
     * Get the customer's email address to associate with Razorpay.
     *
     * @return string|null
     */
    public function RazorpayEmail()
    {
        return $this->email;
    }

    /**
     * Get the customer's country to associate with Razorpay.
     *
     * This needs to be a 2 letter code. See the link below for supported countries.
     *
     * @return string|null
     * @link https://developer.Razorpay.com/reference/platform-parameters/supported-countries
     */
    public function RazorpayCountry()
    {
        //
    }

    /**
     * Get the customer's postal code to associate with Razorpay.
     *
     * See the link below for countries which require this.
     *
     * @return string|null
     * @link https://developer.Razorpay.com/reference/platform-parameters/supported-countries#countries-requiring-postcode
     */
    public function RazorpayPostcode()
    {
        //
    }

These defaults will be used for every action in Cashier that generates a [pay link](#pay-links).

<a name="subscriptions"></a>
## Subscriptions

<a name="creating-subscriptions"></a>
### Creating Subscriptions

To create a subscription, first retrieve an instance of your billable model, which typically will be an instance of `App\Models\User`. Once you have retrieved the model instance, you may use the `newSubscription` method to create the model's subscription pay link:

    use Illuminate\Http\Request;

    Route::get('/user/subscribe', function (Request $request) {
        $payLink = $user->newSubscription('default', $premium = 12345)
            ->create();

        return view('billing', ['payLink' => $payLink]);
    });

The first argument passed to the `newSubscription` method should be the internal name of the subscription. If your application only offers a single subscription, you might call this `default` or `primary`. This subscription name is only for internal application usage and is not meant to be shown to users. In addition, it should not contain spaces and it should never be changed after creating the subscription. The second argument given to the `newSubscription` method is the specific plan the user is subscribing to. This value should correspond to the plan's identifier in Razorpay. The `returnTo` method accepts a URL that your user will be redirected to after they successfully complete the checkout.

The `create` method will create a pay link which you can use to generate a payment button. The payment button can be generated using the `Razorpay-button` [Blade component](/docs/{{version}}/blade#components) that is included with Cashier Razorpay:

```html
<x-Razorpay-button :url="$payLink" class="px-8 py-4">
    Subscribe
</x-Razorpay-button>
```

After the user has finished their checkout, a `subscription_created` webhook will be dispatched from Razorpay. Cashier will receive this webhook and setup the subscription for your customer. In order to make sure all webhooks are properly received and handled by your application, ensure you have properly [setup webhook handling](#handling-Razorpay-webhooks).

<a name="subscriptions-coupons"></a>
#### Coupons

If you would like to apply a coupon when creating the subscription, you may use the `withCoupon` method:

    $payLink = $user->newSubscription('default', $monthly = 12345)
        ->withCoupon('code')
        ->create();

<a name="metadata"></a>
#### Metadata

You can also pass an array of metadata using the `withMetadata` method:

    $payLink = $user->newSubscription('default', $monthly = 12345)
        ->returnTo(route('home'))
        ->withMetadata(['key' => 'value'])
        ->create();

> {note} When providing metadata, please avoid using `subscription_name` as a metadata key. This key is reserved for internal use by Cashier.

<a name="checking-subscription-status"></a>
### Checking Subscription Status

Once a user is subscribed to your application, you may check their subscription status using a variety of convenient methods. First, the `subscribed` method returns `true` if the user has an active subscription, even if the subscription is currently within its trial period:

    if ($user->subscribed('default')) {
        //
    }

The `subscribed` method also makes a great candidate for a [route middleware](/docs/{{version}}/middleware), allowing you to filter access to routes and controllers based on the user's subscription status:

    <?php

    namespace App\Http\Middleware;

    use Closure;

    class EnsureUserIsSubscribed
    {
        /**
         * Handle an incoming request.
         *
         * @param  \Illuminate\Http\Request  $request
         * @param  \Closure  $next
         * @return mixed
         */
        public function handle($request, Closure $next)
        {
            if ($request->user() && ! $request->user()->subscribed('default')) {
                // This user is not a paying customer...
                return redirect('billing');
            }

            return $next($request);
        }
    }

If you would like to determine if a user is still within their trial period, you may use the `onTrial` method. This method can be useful for determining if you should display a warning to the user that they are still on their trial period:

    if ($user->subscription('default')->onTrial()) {
        //
    }

<!--The `subscribedToPlan` method may be used to determine if the user is subscribed to a given plan based on a given Razorpay plan ID. In this example, we will determine if the user's `default` subscription is actively subscribed to the monthly plan:-->

<!--    if ($user->subscribedToPlan($monthly = 12345, 'default')) {-->
<!--        //-->
<!--    }-->

<!--By passing an array to the `subscribedToPlan` method, you may determine if the user's `default` subscription is actively subscribed to the monthly or the yearly plan:-->

<!--    if ($user->subscribedToPlan([$monthly = 12345, $yearly = 54321], 'default')) {-->
<!--        //-->
<!--    }-->

The `recurring` method may be used to determine if the user is currently subscribed and is no longer within their trial period:

    if ($user->subscription('default')->recurring()) {
        //
    }

<a name="cancelled-subscription-status"></a>
#### Cancelled Subscription Status

To determine if the user was once an active subscriber but has cancelled their subscription, you may use the `cancelled` method:

    if ($user->subscription('default')->cancelled()) {
        //
    }

You may also determine if a user has cancelled their subscription, but are still on their "grace period" until the subscription fully expires. For example, if a user cancels a subscription on March 5th that was originally scheduled to expire on March 10th, the user is on their "grace period" until March 10th. Note that the `subscribed` method still returns `true` during this time:

    if ($user->subscription('default')->onGracePeriod()) {
        //
    }

To determine if the user has cancelled their subscription and is no longer within their "grace period", you may use the `ended` method:

    if ($user->subscription('default')->ended()) {
        //
    }

<a name="past-due-status"></a>
#### Past Due Status

If a payment fails for a subscription, it will be marked as `past_due`. When your subscription is in this state it will not be active until the customer has updated their payment information. You may determine if a subscription is past due using the `pastDue` method on the subscription instance:

    if ($user->subscription('default')->pastDue()) {
        //
    }

When a subscription is past due, you should instruct the user to [update their payment information](#updating-payment-information). You may configure how past due subscriptions are handled in your [Razorpay subscription settings](https://vendors.Razorpay.com/subscription-settings).

If you would like subscriptions to still be considered active when they are `past_due`, you may use the `keepPastDueSubscriptionsActive` method provided by Cashier. Typically, this method should be called in the `register` method of your `AppServiceProvider`:

    use Taushifk\Razorpay\Cashier;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Cashier::keepPastDueSubscriptionsActive();
    }

> {note} When a subscription is in a `past_due` state it cannot be changed until payment information has been updated. Therefore, the `swap` and `updateQuantity` methods will throw an exception when the subscription is in a `past_due` state.

<a name="subscription-scopes"></a>
#### Subscription Scopes

Most subscription states are also available as query scopes so that you may easily query your database for subscriptions that are in a given state:

    // Get all active subscriptions...
    $subscriptions = Subscription::query()->active()->get();

    // Get all of the cancelled subscriptions for a user...
    $subscriptions = $user->subscriptions()->cancelled()->get();

A complete list of available scopes is available below:

    Subscription::query()->active();
    Subscription::query()->onTrial();
    Subscription::query()->notOnTrial();
    Subscription::query()->pastDue();
    Subscription::query()->recurring();
    Subscription::query()->ended();
    Subscription::query()->paused();
    Subscription::query()->notPaused();
    Subscription::query()->onPausedGracePeriod();
    Subscription::query()->notOnPausedGracePeriod();
    Subscription::query()->cancelled();
    Subscription::query()->notCancelled();
    Subscription::query()->onGracePeriod();
    Subscription::query()->notOnGracePeriod();

<a name="subscription-single-charges"></a>
### Subscription Single Charges

Subscription single charges allow you to charge subscribers with a one-time charge on top of their subscriptions:

    $response = $user->subscription('default')->charge(12.99, 'Support Add-on');

In contrast to [single charges](#single-charges), this method will immediately charge the customer's stored payment method for the subscription. The charge amount should always be defined in the currency of the subscription.

<a name="updating-payment-information"></a>
### Updating Payment Information

Razorpay always saves a payment method per subscription. If you want to update the default payment method for a subscription, you should first generate a subscription "update URL" using the `updateUrl` method on the subscription model:

    use App\Models\User;

    $user = User::find(1);

    $updateUrl = $user->subscription('default')->updateUrl();

Then, you may use the generated URL in combination with Cashier's provided `Razorpay-button` Blade component to allow the user to initiate the Razorpay widget and update their payment information:

```html
<x-Razorpay-button :url="$updateUrl" class="px-8 py-4">
    Update Card
</x-Razorpay-button>
```

When a user has finished updating their information, a `subscription_updated` webhook will be dispatched by Razorpay and the subscription details will be updated in your application's database.

<a name="changing-plans"></a>
### Changing Plans

After a user has subscribed to your application, they may occasionally want to change to a new subscription plan. To update the subscription plan for a user, you should pass the Razorpay plan's identifier to the subscription's `swap` method:

    use App\Models\User;

    $user = User::find(1);

    $user->subscription('default')->swap($premium = 34567);

If the user is on a trial, the trial period will be maintained. Additionally, if a "quantity" exists for the subscription, that quantity will also be maintained.

If you would like to swap plans and cancel any trial period the user is currently on, you may use the `skipTrial` method:

    $user->subscription('default')
            ->swap($premium = 34567);

If you would like to swap plans and immediately invoice the user instead of waiting for their next billing cycle, you may use the `swapAndInvoice` method:

    $user = User::find(1);

    $user->subscription('default')->swapAndInvoice($premium = 34567);

<a name="subscription-quantity"></a>
### Subscription Quantity

Sometimes subscriptions are affected by "quantity". For example, a project management application might charge $10 per month per project. To easily increment or decrement your subscription's quantity, use the `incrementQuantity` and `decrementQuantity` methods:

    $user = User::find(1);

    $user->subscription('default')->incrementQuantity();

    // Add five to the subscription's current quantity...
    $user->subscription('default')->incrementQuantity(5);

    $user->subscription('default')->decrementQuantity();

    // Subtract five from the subscription's current quantity...
    $user->subscription('default')->decrementQuantity(5);

Alternatively, you may set a specific quantity using the `updateQuantity` method:

    $user->subscription('default')->updateQuantity(10);

<a name="pausing-subscriptions"></a>
### Pausing Subscriptions

To pause a subscription, call the `pause` method on the user's subscription:

    $user->subscription('default')->pause();

When a subscription is paused, Cashier will automatically set the `paused_from` column in your database. This column is used to know when the `paused` method should begin returning `true`. For example, if a customer pauses a subscription on March 1st, but the subscription was not scheduled to recur until March 5th, the `paused` method will continue to return `false` until March 5th. This is done because a user is typically allowed to continue using an application until the end of their billing cycle.

You may determine if a user has paused their subscription but are still on their "grace period" using the `onPausedGracePeriod` method:

    if ($user->subscription('default')->onPausedGracePeriod()) {
        //
    }

To resume a paused a subscription, you may call the `unpause` method on the user's subscription:

    $user->subscription('default')->unpause();

> {note} A subscription cannot be modified while it is paused. If you want to swap to a different plan or update quantities you must resume the subscription first.

<a name="cancelling-subscriptions"></a>
### Cancelling Subscriptions

To cancel a subscription, call the `cancel` method on the user's subscription:

    $user->subscription('default')->cancel();

When a subscription is cancelled, Cashier will automatically set the `ends_at` column in your database. This column is used to know when the `subscribed` method should begin returning `false`. For example, if a customer cancels a subscription on March 1st, but the subscription was not scheduled to end until March 5th, the `subscribed` method will continue to return `true` until March 5th. This is done because a user is typically allowed to continue using an application until the end of their billing cycle.

You may determine if a user has cancelled their subscription but are still on their "grace period" using the `onGracePeriod` method:

    if ($user->subscription('default')->onGracePeriod()) {
        //
    }

If you wish to cancel a subscription immediately, you may call the `cancelNow` method on the user's subscription:

    $user->subscription('default')->cancelNow();

> {note} Razorpay's subscriptions cannot be resumed after cancellation. If your customer wishes to resume their subscription, they will have to subscribe to a new subscription.

<a name="subscription-trials"></a>
## Subscription Trials

<a name="with-payment-method-up-front"></a>
### With Payment Method Up Front

> {note} While trialing and collecting payment method details up front, Razorpay prevents any subscription changes such as swapping plans or updating quantities. If you want to allow a customer to swap plans during a trial the subscription must be cancelled and recreated.

If you would like to offer trial periods to your customers while still collecting payment method information up front, you should use the `trialDays` method when creating your subscription pay links:

    use Illuminate\Http\Request;

    Route::get('/user/subscribe', function (Request $request) {
        $payLink = $request->user()->newSubscription('default', $monthly = 12345)
                    ->trialDays(10)
                    ->create();

        return view('billing', ['payLink' => $payLink]);
    });

This method will set the trial period ending date on the subscription record within your application's database, as well as instruct Razorpay to not begin billing the customer until after this date.

> {note} If the customer's subscription is not cancelled before the trial ending date they will be charged as soon as the trial expires, so you should be sure to notify your users of their trial ending date.

You may determine if the user is within their trial period using either the `onTrial` method of the user instance or the `onTrial` method of the subscription instance. The two examples below are equivalent:

    if ($user->onTrial('default')) {
        //
    }

    if ($user->subscription('default')->onTrial()) {
        //
    }

<a name="defining-trial-days-in-Razorpay-cashier"></a>
#### Defining Trial Days In Razorpay / Cashier

You may choose to define how many trial days your plan's receive in the Razorpay dashboard or always pass them explicitly using Cashier. If you choose to define your plan's trial days in Razorpay you should be aware that new subscriptions, including new subscriptions for a customer that had a subscription in the past, will always receive a trial period unless you explicitly call the `trialDays(0)` method.

<a name="handling-Razorpay-webhooks"></a>
## Handling Razorpay Webhooks

Razorpay can notify your application of a variety of events via webhooks. By default, a route that points to Cashier's webhook controller is registered by the Cashier service provider. This controller will handle all incoming webhook requests.

By default, this controller will automatically handle cancelling subscriptions that have too many failed charges ([as defined by your Razorpay subscription settings](https://vendors.Razorpay.com/subscription-settings)), subscription updates, and payment method changes; however, as we'll soon discover, you can extend this controller to handle any Razorpay webhook event you like.

To ensure your application can handle Razorpay webhooks, be sure to [configure the webhook URL in the Razorpay control panel](https://vendors.Razorpay.com/alerts-webhooks). By default, Cashier's webhook controller responds to the `/Razorpay/webhook` URL path. The full list of all webhooks you should enable in the Razorpay control panel are:

- Subscription authenticated
- Subscription Updated
- subscription Paused
- subscription Resumed
- subscription Halted
- Subscription Cancelled
- Subscription Completed
- Invoice Paid

> {note} Make sure you protect incoming requests with Cashier's included [webhook signature verification](/docs/{{version}}/cashier-Razorpay#verifying-webhook-signatures) middleware.

<a name="webhooks-csrf-protection"></a>
#### Webhooks & CSRF Protection

Since Razorpay webhooks need to bypass Laravel's [CSRF protection](/docs/{{version}}/csrf), be sure to list the URI as an exception in your `App\Http\Middleware\VerifyCsrfToken` middleware or list the route outside of the `web` middleware group:

    protected $except = [
        'razorpay/*',
    ];

<a name="defining-webhook-event-handlers"></a>
### Defining Webhook Event Handlers

Cashier automatically handles subscription cancellation on failed charges and other common Razorpay webhooks. However, if you have additional webhook events you would like to handle, you may do so by listening to the following events that are dispatched by Cashier:

- `Taushifk\Razorpay\Events\WebhookReceived`
- `Taushifk\Razorpay\Events\WebhookHandled`

Both events contain the full payload of the Razorpay webhook. For example, if you wish to handle the `invoice.payment_succeeded` webhook, you may register a [listener](/docs/{{version}}/events#defining-listeners) that will handle the event:

    <?php

    namespace App\Listeners;

    use Taushifk\Razorpay\Events\WebhookReceived;

    class RazorpayEventListener
    {
        /**
         * Handle received Razorpay webhooks.
         *
         * @param  \Taushifk\Razorpay\Events\WebhookReceived  $event
         * @return void
         */
        public function handle(WebhookReceived $event)
        {
            if ($event->payload['event'] === 'invoice.paid') {
                // Handle the incoming event...
            }
        }
    }

Once your listener has been defined, you may register it within your application's `EventServiceProvider`:

    <?php

    namespace App\Providers;

    use App\Listeners\RazorpayEventListener;
    use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
    use Taushifk\Razorpay\Events\WebhookReceived;

    class EventServiceProvider extends ServiceProvider
    {
        protected $listen = [
            WebhookReceived::class => [
                RazorpayEventListener::class,
            ],
        ];
    }

Cashier also emit events dedicated to the type of the received webhook. In addition to the full payload from Razorpay, they also contain the relevant models that were used to process the webhook such as the billable model, the subscription, or the receipt:

<div class="content-list" markdown="1">
- `Taushifk\Razorpay\Events\PaymentSucceeded`
- `Taushifk\Razorpay\Events\SubscriptionPaymentSucceeded`
- `Taushifk\Razorpay\Events\SubscriptionCreated`
- `Taushifk\Razorpay\Events\SubscriptionUpdated`
- `Taushifk\Razorpay\Events\SubscriptionCancelled`
</div>

You can also override the default, built-in webhook route by defining the `CASHIER_WEBHOOK` environment variable in your application's `.env` file. This value should be the full URL to your webhook route and needs to match the URL set in your Razorpay control panel:

```bash
CASHIER_WEBHOOK=https://example.com/my-Razorpay-webhook-url
```

<a name="verifying-webhook-signatures"></a>
### Verifying Webhook Signatures

To secure your webhooks, you may use [Razorpay's webhook signatures](https://developer.Razorpay.com/webhook-reference/verifying-webhooks). For convenience, Cashier automatically includes a middleware which validates that the incoming Razorpay webhook request is valid.

To enable webhook verification, ensure that the `Razorpay_PUBLIC_KEY` environment variable is defined in your application's `.env` file. The public key may be retrieved from your Razorpay account dashboard.

<a name="single-charges"></a>
## Single Charges

<a name="simple-charge"></a>
### Simple Charge

If you would like to make a one-time charge against a customer, you may use the `charge` method on a billable model instance to generate a pay link for the charge. The `charge` method accepts the charge amount (float) as its first argument and a charge description as its second argument:

    use Illuminate\Http\Request;

    Route::get('/store', function (Request $request) {
        return view('store', [
            'payLink' => $user->charge(10, 'Action Figure')
        ]);
    });

After generating the pay link, you may use Cashier's provided `Razorpay-button` Blade component to allow the user to initiate the Razorpay widget and complete the charge:

```html
<x-Razorpay-button :url="$payLink" class="px-8 py-4">
    Buy
</x-Razorpay-button>
```

The `charge` method accepts an array as its third argument, allowing you to pass any options you wish to the underlying Razorpay pay link creation. Please consult [the Razorpay documentation](https://developer.Razorpay.com/api-reference/product-api/pay-links/createpaylink) to learn more about the options available to you when creating charges:

    $payLink = $user->charge(10, 'Action Figure', [
        'custom_option' => $value,
    ]);

Charges happen in the currency specified in the `cashier.currency` configuration option. By default, this is set to USD. You may override the default currency by defining the `CASHIER_CURRENCY` environment variable in your application's `.env` file:

```bash
CASHIER_CURRENCY=EUR
```

<a name="refunding-orders"></a>
### Refunding Orders

If you need to refund a Razorpay order, you may use the `refund` method. This method accepts the Razorpay order ID as its first argument. You may retrieve the receipts for a given billable model using the `receipts` method:

    use App\Models\User;

    $user = User::find(1);

    $receipt = $user->receipts()->first();

    $refundRequestId = $user->refund($receipt->order_id);

You may optionally specify a specific amount to refund as well as a reason for the refund:

    $receipt = $user->receipts()->first();

    $refundRequestId = $user->refund(
        $receipt->order_id, 5.00, 'Unused product time'
    );

> {tip} You can use the `$refundRequestId` as a reference for the refund when contacting Razorpay support.

<a name="receipts"></a>
## Receipts

You may easily retrieve an array of a billable model's receipts via the `receipts` property:

    use App\Models\User;

    $user = User::find(1);

    $receipts = $user->receipts;

When listing the receipts for the customer, you may use the receipt instance's methods to display the relevant receipt information. For example, you may wish to list every receipt in a table, allowing the user to easily download any of the receipts:

```html
<table>
    @foreach ($receipts as $receipt)
        <tr>
            <td>{{ $receipt->paid_at->toFormattedDateString() }}</td>
            <td>{{ $receipt->amount() }}</td>
            <td><a href="{{ $receipt->receipt_url }}" target="_blank">Download</a></td>
        </tr>
    @endforeach
</table>
```

<a name="past-and-upcoming-payments"></a>
### Past & Upcoming Payments

You may use the `lastPayment` and `nextPayment` methods to retrieve and display a customer's past or upcoming payments for recurring subscriptions:

    use App\Models\User;

    $user = User::find(1);

    $subscription = $user->subscription('default');

    $lastPayment = $subscription->lastPayment();
    $nextPayment = $subscription->nextPayment();

Both of these methods will return date 

<a name="handling-failed-payments"></a>
## Handling Failed Payments

Subscription payments fail for various reasons, such as expired cards or a card having insufficient funds. When this happens, we recommend that you let Razorpay handle payment failures for you. Specifically, you may [setup Razorpay's automatic billing emails](https://vendors.Razorpay.com/subscription-settings) in your Razorpay dashboard.

Alternatively, you can perform more precise customization by catching the [`subscription_payment_failed`](https://developer.Razorpay.com/webhook-reference/subscription-alerts/subscription-payment-failed) webhook and enabling the "Subscription Payment Failed" option in the Webhook settings of your Razorpay dashboard:

    <?php

    namespace App\Http\Controllers;

    use Taushifk\Razorpay\Http\Controllers\WebhookController as CashierController;

    class WebhookController extends CashierController
    {
        /**
         * Handle subscription payment failed.
         *
         * @param  array  $payload
         * @return void
         */
        public function handleSubscriptionPaymentFailed($payload)
        {
            // Handle the failed subscription payment...
        }
    }

<a name="testing"></a>
## Testing

While testing, you should manually test your billing flow to make sure your integration works as expected.

For automated tests, including those executed within a CI environment, you may use [Laravel's HTTP Client](/docs//{{version}}/http-client#testing) to fake HTTP calls made to Razorpay. Although this does not test the actual responses from Razorpay, it does provide a way to test your application without actually calling Razorpay's API.