<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReceiptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('razorpay_subscription_id')->nullable()->index();
            $table->string('payment_id');
            $table->string('order_id')->unique();
            $table->string('amount');
            $table->string('tax');
            $table->string('currency', 3);
            $table->integer('quantity');
            $table->string('receipt_url')->unique();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('receipts');
    }
}
