<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('variant_colors', function (Blueprint $table) {
            $table->id();
            $table->integer('variant_id');
            $table->integer('color_id');
            $table->integer('quantity');
            $table->bigInteger('price');
            $table->bigInteger('offer_price')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_colors');
    }
};