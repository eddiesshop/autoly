<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNameIndexToGithubRepositoryBranchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE `github_repository_branches` ALTER `name` DROP DEFAULT;");
        DB::statement("ALTER TABLE `github_repository_branches` CHANGE COLUMN `name` `name` VARCHAR(255) NOT NULL COLLATE 'utf8_bin' AFTER `repository_id`;");

        Schema::table('github_repository_branches', function (Blueprint $table) {
            //
            $table->index('name', 'branch_name_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `github_repository_branches` ALTER `name` DROP DEFAULT;");
        DB::statement("ALTER TABLE `github_repository_branches` CHANGE COLUMN `name` `name` VARCHAR(255) NOT NULL COLLATE 'utf8_unicode_ci' AFTER `repository_id`;");

        Schema::table('github_repository_branches', function (Blueprint $table) {
            $table->dropIndex('branch_name_index');
        });
    }
}
