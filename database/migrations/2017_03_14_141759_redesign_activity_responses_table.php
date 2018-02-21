<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RedesignActivityResponsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activity_responses', function (Blueprint $table) {
            //
            $table->integer('user_id', false, true)->after('id');

            $table->foreign('user_id', 'user_response_fk')
                ->references('id')
                ->on('users')
                ->onUpdate('NO ACTION')
                ->onDelete('NO ACTION');

            $table->rename('user_responses');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activity_responses', function (Blueprint $table) {
            //
            $table->dropColumn('user_id');
            $table->dropForeign('user_response_fk');
            $table->rename('activity_responses');

        });
    }
}
