<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApiAuthorizationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_authorizations', function (Blueprint $table) {
            $table->id();
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->string('name');
            $table->enum('type', ['domain', 'ip'])->index();
            $table->string('value')->index();
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
        Schema::dropIfExists('api_authorizations');
    }
}
