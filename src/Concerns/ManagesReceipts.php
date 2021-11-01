<?php

namespace Taushifk\Razorpay\Concerns;

use Taushifk\Razorpay\Cashier;

trait ManagesReceipts
{
    /**
     * Get all of the receipts for the Billable model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function receipts()
    {
        return $this->hasMany(Cashier::$receiptModel)->orderByDesc('created_at');
    }
}
