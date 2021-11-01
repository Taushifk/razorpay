<form action="" method="POST">
<script
    src="https://checkout.razorpay.com/v1/checkout.js"
    data-key="{{ config('cashier.key') }}"
    data-order_id="{{ $orderId }}"
    data-buttontext="Pay with Razorpay"
    data-prefill.name="{{ auth()->user()->name }}"
    data-prefill.email="{{ auth()->user()->email }}"
></script>
</form>
