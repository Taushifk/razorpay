<?php

namespace Taushifk\Razorpay\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Taushifk\Razorpay\Cashier;
//use Taushifk\Razorpay\Exceptions\CustomerAlreadyCreated;
//use Laravel\Cashier\Exceptions\InvalidCustomer;
use Razorpay\Customer as RazorpayCustomer;
//use Razorpay\Exception\InvalidRequestException as RazorpayInvalidRequestException;

trait ManagesCustomer
{
    /**
     * Retrieve the Razorpay customer ID.
     *
     * @return string|null
     */
    public function razorpayId()
    {
        return $this->razorpay_id;
    }

    /**
     * Determine if the customer has a Razorpay customer ID.
     *
     * @return bool
     */
    public function hasRazorpayId()
    {
        return ! is_null($this->razorpay_id);
    }

    /**
     * Determine if the customer has a Razorpay customer ID and throw an exception if not.
     *
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidCustomer
     */
    protected function assertCustomerExists()
    {
        if (! $this->hasRazorpayId()) {
            throw InvalidCustomer::notYetCreated($this);
        }
    }

    /**
     * Create a Razorpay customer for the given model.
     *
     * @param  array  $options
     * @return \Razorpay\Customer
     *
     * @throws \Laravel\Cashier\Exceptions\CustomerAlreadyCreated
     */
    public function createAsRazorpayCustomer(array $options = [])
    {
        if ($this->hasRazorpayId()) {
            throw CustomerAlreadyCreated::exists($this);
        }

        if (! array_key_exists('name', $options) && $name = $this->RazorpayName()) {
            $options['name'] = $name;
        }

        if (! array_key_exists('email', $options) && $email = $this->RazorpayEmail()) {
            $options['email'] = $email;
        }

        if (! array_key_exists('phone', $options) && $phone = $this->RazorpayPhone()) {
            $options['contact'] = $phone;
        }

        /*if (! array_key_exists('gstin', $options) && $address = $this->RazorpayGstin()) {
            $options['gstin'] = $address;
        }*/

        // Here we will create the customer instance on Razorpay and store the ID of the
        // user from Razorpay. This ID will correspond with the Razorpay user instances
        // and allow us to retrieve users from Razorpay later when we need to work.
        $customer = $this->Razorpay()->customer->create($options);

        $this->razorpay_id = $customer->id;

        $this->save();

        return $customer;
    }

    /**
     * Update the underlying Razorpay customer information for the model.
     *
     * @param  array  $options
     * @return \Razorpay\Customer
     */
    public function updateRazorpayCustomer(array $options = [])
    {
        return $this->Razorpay()->customer
        ->fetch($this->razorpay_id)
        ->edit($options);
    }

    /**
     * Get the Razorpay customer instance for the current user or create one.
     *
     * @param  array  $options
     * @return \Razorpay\Customer
     */
    public function createOrGetRazorpayCustomer(array $options = [])
    {
        if ($this->hasRazorpayId()) {
            return $this->asRazorpayCustomer();
        }

        return $this->createAsRazorpayCustomer($options);
    }

    /**
     * Get the Razorpay customer for the model.
     *
     * @param  array  $expand
     * @return \Razorpay\Customer
     */
    public function asRazorpayCustomer(array $expand = [])
    {
        $this->assertCustomerExists();

        return $this->Razorpay()->customer
        ->fetch($this->razorpay_id);
    }

    /**
     * Get the name that should be synced to Razorpay.
     *
     * @return string|null
     */
    public function RazorpayName()
    {
        return $this->name;
    }

    /**
     * Get the email address that should be synced to Razorpay.
     *
     * @return string|null
     */
    public function RazorpayEmail()
    {
        return $this->email;
    }

    /**
     * Get the phone number that should be synced to Razorpay.
     *
     * @return string|null
     */
    public function RazorpayPhone()
    {
        return $this->phone;
    }

    /**
     * Get the address that should be synced to Razorpay.
     *
     * @return array|null
     */
    public function RazorpayAddress()
    {
        // return [
        //     'city' => 'Little Rock',
        //     'country' => 'US',
        //     'line1' => '1 Main St.',
        //     'line2' => 'Apartment 5',
        //     'postal_code' => '72201',
        //     'state' => 'Arkansas',
        // ];
    }

    /**
     * Sync the customer's information to Razorpay.
     *
     * @return \Razorpay\Customer
     */
    public function syncRazorpayCustomerDetails()
    {
        return $this->updateRazorpayCustomer([
            'name' => $this->RazorpayName(),
            'email' => $this->RazorpayEmail(),
            'phone' => $this->RazorpayPhone(),
            'address' => $this->RazorpayAddress(),
        ]);
    }

    /**
     * Get the Razorpay supported currency used by the customer.
     *
     * @return string
     */
    public function preferredCurrency()
    {
        return config('cashier.currency');
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount, $this->preferredCurrency());
    }

    /**
     * Get the Razorpay SDK client.
     *
     * @param  array  $options
     * @return \Razorpay\RazorpayClient
     */
    public static function Razorpay(array $options = [])
    {
        return Cashier::Razorpay($options);
    }
}