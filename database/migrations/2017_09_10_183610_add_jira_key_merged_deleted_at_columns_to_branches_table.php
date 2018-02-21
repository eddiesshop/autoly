<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddJiraKeyMergedDeletedAtColumnsToBranchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('github_repository_branches', function (Blueprint $table) {
            //
            $table->boolean('merged')->default(false)->after('html_url');
            $table->string('jira_key')->nullable()->after('html_url');
            $table->softDeletes();
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
            //
            $table->dropColumn(['merged', 'jira_key']);
            $table->dropSoftDeletes();
        });
    }
}
