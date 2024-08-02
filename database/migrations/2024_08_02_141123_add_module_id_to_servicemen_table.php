<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddModuleIdToServicemenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('servicemen', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->nullable()->after('id');

            // Optionally, add a foreign key constraint
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('servicemen', function (Blueprint $table) {
            // Remove the foreign key constraint first
            $table->dropForeign(['module_id']);
            $table->dropColumn('module_id');
        });
    }
}
