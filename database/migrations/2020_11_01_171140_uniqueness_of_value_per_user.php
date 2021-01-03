<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UniquenessOfValuePerUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('api_authorizations', function (Blueprint $table) {
            $table->unique(['value', 'user_id'], 'unique_value_per_user');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('api_authorizations', function (Blueprint $table) {
            $table->dropIndex('unique_value_per_user');
        });
    }
}
