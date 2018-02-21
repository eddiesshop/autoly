<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateActivityDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('activity_data', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('activity_id');
            $table->integer('directive_id');
            $table->string('response', 65000); //Will want to change this to JSON when upgrade to PHP is possible on server
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('activity_data');
    }
}
