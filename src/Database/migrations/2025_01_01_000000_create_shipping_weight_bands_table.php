<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_weight_bands', function (Blueprint $table) {
            $table->id();
            $table->decimal('max_kg', 8, 3)->comment('Upper weight limit in kg for this band');
            $table->decimal('rate_per_kg', 8, 4)->comment('£ per kg charged for this band');
            $table->string('label')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_weight_bands');
    }
};
