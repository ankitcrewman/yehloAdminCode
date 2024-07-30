<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToServicemenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('servicemen', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->after('password');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->string('module_type')->after('store_id');
            $table->string('status')->after('module_type');
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
            $table->dropForeign(['store_id']);
            $table->dropColumn(['store_id', 'module_type', 'status']);
        });
    }
}
