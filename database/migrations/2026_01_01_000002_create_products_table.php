<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('version', 20)->default('1.0.0');
            $table->string('sku', 50)->unique()->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('trial_days', 5, 0)->default(0);
            $table->string('icon')->nullable();
            $table->string('screenshot')->nullable();
            $table->enum('type', ['standard', 'pro', 'enterprise'])->default('standard');
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->boolean('is_downloadable')->default(false);
            $table->boolean('requires_activation')->default(true);
            $table->integer('max_domains')->default(1);
            $table->json('features')->nullable();
            $table->json('requirements')->nullable();
            $table->text('changelog')->nullable();
            $table->text('installation_guide')->nullable();
            $table->string('download_url')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('type');
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('product_categories')->nullOnDelete();
        });

        Schema::create('product_category_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'product_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_product');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('products');
    }
};
