<?php

namespace Taushifk\Razorpay;

use Taushifk\Razorpay\Concerns\ManagesCustomer;
use Taushifk\Razorpay\Concerns\ManagesReceipts;
use Taushifk\Razorpay\Concerns\ManagesSubscriptions;
use Taushifk\Razorpay\Concerns\PerformsCharges;

trait Billable
{
    use ManagesCustomer;
    use ManagesSubscriptions;
    use ManagesReceipts;
    use PerformsCharges;
}
