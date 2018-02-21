<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDirectivesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('directives', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('status_id', false, true);
            $table->string('action');
            $table->string('command')->nullable();
            $table->string('example_param')->nullable();
            $table->string('description');
            $table->integer('order', false, true);
            $table->boolean('immutable');
            $table->boolean('nixable');
            $table->timestamps();

            $table->foreign('status_id')
                ->references('id')
                ->on('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('directives');
    }
}
