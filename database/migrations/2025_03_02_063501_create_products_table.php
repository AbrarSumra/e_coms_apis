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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('image_url')->nullable();
            $table->json('gallery_urls')->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->unsignedBigInteger('misc_id')->nullable();
            $table->string('brand')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('reviews_count')->default(0);
            $table->integer('item_qty')->default(1);
            $table->timestamps();
            $table->integer('inventory_quantity')->default(0);
            $table->integer('low_stock_threshold')->nullable();
            $table->string('sku')->nullable();
            $table->boolean('is_in_stock')->default(true);
            $table->boolean('manage_stock')->default(false);
            $table->boolean('wishlist_liked')->default(false);
        
            // Foreign Keys
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('sub_category_id')->references('id')->on('sub_categories')->onDelete('cascade');
            $table->foreign('misc_id')->references('id')->on('misc_categories')->onDelete('set null');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
