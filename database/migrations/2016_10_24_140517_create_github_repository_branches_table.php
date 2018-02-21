<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGithubRepositoryBranchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('github_repository_branches', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('repository_id', false, true);
            $table->string('name');
            $table->string('sha1');
            $table->string('url');
            $table->timestamps();

            $table->foreign('repository_id', 'branch_repo_fk')
                ->references('github_id')
                ->on('github_repositories');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('github_repository_branches');
    }
}
