<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddServicemanIdToDeliveryManWalletsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('delivery_man_wallets', function (Blueprint $table) {
            // Add the serviceman_id column
            $table->unsignedBigInteger('serviceman_id')->after('delivery_man_id')->nullable();
            // Optionally, if serviceman_id should reference another table, you can add a foreign key constraint here
            // $table->foreign('serviceman_id')->references('id')->on('other_table')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('delivery_man_wallets', function (Blueprint $table) {
            // Remove the serviceman_id column
            $table->dropColumn('serviceman_id');
        });
    }
}
