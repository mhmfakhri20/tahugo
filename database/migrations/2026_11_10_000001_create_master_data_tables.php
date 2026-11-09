<?php

use App\Enums\ItemCategory;
use App\Enums\ProductType;
use App\Enums\SalesChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master data tables (PRD §6.2, §9).
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('unit', 20);
            $table->enum('category', ItemCategory::values());
            $table->unsignedBigInteger('last_purchase_price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('item_min_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_qty', 12, 3);
            $table->timestamps();

            $table->unique(['item_id', 'location_id']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('type', ProductType::values());
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', SalesChannel::values());
            $table->unsignedBigInteger('price');
            $table->timestamps();

            $table->unique(['product_id', 'channel']);
        });

        Schema::create('product_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 3);
            $table->timestamps();

            $table->unique(['product_id', 'item_id']);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('product_recipes');
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('products');
        Schema::dropIfExists('item_min_stocks');
        Schema::dropIfExists('items');
    }
};
