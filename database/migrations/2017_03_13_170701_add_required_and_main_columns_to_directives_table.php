<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRequiredAndMainColumnsToDirectivesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('directives', function (Blueprint $table) {
            //
            $table->boolean('main')->after('order');
            $table->boolean('required')->after('main');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('directives', function (Blueprint $table) {
            //
            $table->dropColumn('required');
            $table->dropColumn('main');
        });
    }
}
