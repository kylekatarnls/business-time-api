<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApiAuthorizationQuotaNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_authorization_quota_notifications', static function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at', 6);
            $table->mediumInteger('year', false, true);
            $table->tinyInteger('month', false, true);
            $table->tinyInteger('percentage', false, true);
            $table->bigInteger('user_id', false, true);
            $table->bigInteger('api_authorization_id', false, true);
            $table->index(['year', 'month', 'user_id'], 'monthly_user_quota');
            $table->unique(['year', 'month', 'percentage', 'api_authorization_id'], 'monthly_threshold');
            $table->foreign('user_id', 'auth_quota_user')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('api_authorization_id', 'auth_quota_auth')
                ->references('id')
                ->on('api_authorizations')
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
        Schema::dropIfExists('api_authorization_quota_notifications');
    }
}
