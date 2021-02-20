<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDailyFilteredLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('daily_filtered_log', static function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->enum('key', ['code', 'ville', 'ip', 'domain'])->index();
            $table->string('value', 220)->index();
            $table->bigInteger('count')->unsigned();
            $table->unique(['date', 'key', 'value'], 'unique_pair_per_day');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('daily_filtered_log');
    }
}
