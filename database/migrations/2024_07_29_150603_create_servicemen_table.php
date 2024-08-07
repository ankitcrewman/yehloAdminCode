<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServicemenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('servicemen', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_number');
            $table->string('serviceman_image')->nullable();
            $table->string('document_type')->nullable();
            $table->string('identity_number')->nullable();
            $table->string('identification_image')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('module_type'); // Adding module_type
            $table->unsignedBigInteger('store_id'); // Adding store_id
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade'); // Adding foreign key constraint
            $table->string('status'); // Adding status
            $table->timestamps();
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
            $table->dropForeign(['store_id']); // Dropping the foreign key constraint
        });

        Schema::dropIfExists('servicemen');
    }
}
