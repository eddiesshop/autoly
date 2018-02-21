<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGithubRepositoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('github_repositories', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('github_id', false, true);
            $table->string('name');
            $table->string('full_name');
            $table->string('owner');
            $table->integer('owner_id', false, true);
            $table->timestampTz('created');
            $table->timestamps();
            
            $table->unique('github_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('github_repositories');
    }
}
