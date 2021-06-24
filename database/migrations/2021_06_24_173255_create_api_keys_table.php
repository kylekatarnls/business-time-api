<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApiKeysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->string('key')->unique();
        });
        Schema::create('api_key_user', function (Blueprint $table) {
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('api_key_id')->constrained()->onDelete('cascade');
            $table->primary(['user_id', 'api_key_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('user_api_keys');
    }
}
