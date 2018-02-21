<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateActivityResponsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('activity_responses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('activity_id', false, true)->nullable();
            $table->string('response');
            $table->timestamps();

            $table->foreign('activity_id')
                ->references('id')
                ->on('activity');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('activity_responses');
    }
}
