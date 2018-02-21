<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEnvironmentCommandsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('environment_commands', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('type', ['single', 'group']);
            $table->string('environment');
            $table->integer('repository_id', false, true);
            $table->integer('status_id', false, true);
            $table->string('env_var');
            $table->integer('order');
            $table->timestamps();

            $table->foreign('repository_id', 'env_repo_fk')
                ->references('github_id')
                ->on('github_repositories');

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
        Schema::drop('environment_commands');
    }
}
