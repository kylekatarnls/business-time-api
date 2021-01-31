<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionQuotaNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscription_quota_notifications', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at', 6);
            $table->mediumInteger('year', false, true);
            $table->tinyInteger('month', false, true);
            $table->tinyInteger('percentage', false, true);
            $table->bigInteger('user_id', false, true);
            $table->bigInteger('subscription_id', false, true);
            $table->index(['year', 'month', 'user_id'], 'monthly_user_quota');
            $table->unique(['year', 'month', 'percentage', 'subscription_id'], 'monthly_threshold');
            $table->foreign('user_id', 'sub_quota_user')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('subscription_id', 'sub_quota_sub')
                ->references('id')
                ->on('subscriptions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscription_quota_notifications');
    }
}
