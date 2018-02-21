<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_services', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id', false, true);
            $table->enum('service_type', ['J', 'G', 'S-U', 'S-I']);//(J)ira, (G)ithub, (S-U)Slack-Username, (S-I)Slack-ID
            $table->string('user_name');
            $table->string('access_token');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')->on('users')
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
        Schema::drop('user_services');
    }
}
