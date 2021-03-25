<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        try {
            Schema::create('log', static function (Blueprint $table) {
                $table->id();
                $table->timestamp('date')->index();
                $table->string('ip', 255)->index();
                $table->string('code', 10)->index();
                $table->string('ville', 255)->index();
                $table->text('referer');
                $table->string('domain', 255)->index();
            });
        } catch (QueryException) {
            DB::statement('CREATE TABLE IF NOT EXISTS `log` (
              `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `date` datetime NOT NULL,
              `ip` varchar(255) COLLATE utf8_bin NOT NULL,
              `code` varchar(10) COLLATE utf8_bin NOT NULL,
              `ville` varchar(255) COLLATE utf8_bin NOT NULL,
              `referer` text COLLATE utf8_bin NOT NULL,
              `domain` varchar(255) COLLATE utf8_bin NOT NULL,
              PRIMARY KEY (`id`),
              KEY `date` (`date`),
              KEY `ip` (`ip`),
              KEY `ville` (`ville`),
              KEY `code` (`code`),
              KEY `domain` (`domain`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('log');
    }
}
