<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveShaColumnAddUrlColumnsToGithubRepositoryBranchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('github_repository_branches', function (Blueprint $table) {
            $table->dropColumn('sha1');
            $table->renameColumn('url', 'api_url');
        });

	Schema::table('github_repository_branches', function (Blueprint $table) {
            $table->string('html_url')->after('api_url');
        });
 
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('github_repository_branches', function (Blueprint $table) {
            //Not going to roll back
        });
    }
}
