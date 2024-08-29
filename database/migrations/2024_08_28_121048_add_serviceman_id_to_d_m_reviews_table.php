<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddServicemanIdToDMReviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('d_m_reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('serviceman_id')->after('delivery_man_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('d_m_reviews', function (Blueprint $table) {
            $table->dropColumn('serviceman_id');
        });
    }
}
