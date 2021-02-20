<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('refunds', static function (Blueprint $table) {
            $table->id();
            $table->string('stripe_refund_id')->unique();
            $table->string('payment_intent')->index();
            $table->string('balance_transaction')->index();
            $table->string('charge')->index();
            $table->string('status')->index();
            $table->string('currency')->index();
            $table->bigInteger('cents_amount')->index();
            $table->timestamps();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('refunds');
    }
}
