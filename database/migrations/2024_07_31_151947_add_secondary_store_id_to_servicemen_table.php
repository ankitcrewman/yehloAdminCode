<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSecondaryStoreIdToServicemenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('servicemen', function (Blueprint $table) {
            $table->unsignedBigInteger('secondary_store_id')->nullable()->after('store_id');
            $table->foreign('secondary_store_id')->references('id')->on('stores')->onDelete('cascade');
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
            $table->dropForeign(['secondary_store_id']);
            $table->dropColumn('secondary_store_id');
        });
    }
}
