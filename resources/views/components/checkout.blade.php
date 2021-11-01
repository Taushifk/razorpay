<div {{ $attributes->merge(['class' => $id]) }}></div>
<script type="text/javascript">
    var rzp1 = new Razorpay(@json($options()));
    rzp1.open();
</script>
