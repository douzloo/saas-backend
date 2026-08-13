<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // Step 1: Create product_releases table
        // ============================================================
        Schema::create('product_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('version', 20);
            $table->enum('channel', ['stable', 'beta', 'alpha', 'nightly'])->default('stable');
            $table->enum('status', ['draft', 'published', 'deprecated', 'rolled_back'])->default('draft');
            $table->longText('release_notes')->nullable();
            $table->text('changelog')->nullable();
            $table->boolean('is_force_update')->default(false);
            $table->string('min_app_version', 20)->nullable();
            $table->string('max_app_version', 20)->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('deprecated_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'version']);
            $table->index(['product_id', 'status']);
            $table->index(['product_id', 'channel']);
            $table->index('status');
        });

        // ============================================================
        // Step 2: Add columns to downloads table
        // ============================================================
        Schema::table('downloads', function (Blueprint $table) {
            $table->foreignId('product_release_id')->nullable()->after('id')->constrained('product_releases')->nullOnDelete();
            $table->enum('platform', ['windows', 'macos', 'linux', 'web', 'source', 'universal'])->default('universal')->after('product_release_id');
            $table->enum('status', ['available', 'deprecated', 'removed'])->default('available')->after('platform');
        });

        // ============================================================
        // Step 3: Add columns to products table
        // ============================================================
        Schema::table('products', function (Blueprint $table) {
            $table->enum('activation_strategy', ['none', 'domain', 'machine', 'hybrid'])->nullable()->after('requires_activation');
            $table->integer('default_max_activations')->default(1)->after('activation_strategy');
            $table->string('latest_release_version', 20)->nullable()->after('default_max_activations');
            $table->string('category', 50)->nullable()->after('latest_release_version');
        });

        // ============================================================
        // Step 4: Add identifier column to license_activations table
        // ============================================================
        Schema::table('license_activations', function (Blueprint $table) {
            $table->string('identifier', 255)->nullable()->after('domain');
        });

        // ============================================================
        // Step 5: Modify settings table (production-safe constraint swap)
        // ============================================================

        // Check for duplicate keys before modifying constraint
        $duplicates = DB::select('
            SELECT `key`, COUNT(*) as cnt 
            FROM `settings` 
            GROUP BY `key` 
            HAVING cnt > 1
        ');

        if (count($duplicates) > 0) {
            throw new RuntimeException(
                'Duplicate settings keys found. Resolve before migration. Keys: '.
                implode(', ', array_column($duplicates, 'key'))
            );
        }

        Schema::table('settings', function (Blueprint $table) {
            // Add new column first
            $table->foreignId('product_id')->nullable()->after('id')->constrained('products')->nullOnDelete();

            // Drop old unique constraint on 'key' alone
            $table->dropUnique(['key']);

            // Add new composite unique constraint
            $table->unique(['key', 'product_id']);
        });

        // ============================================================
        // Step 6: Create product_assignments table
        // ============================================================
        Schema::create('product_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['viewer', 'support', 'manager', 'admin'])->default('support');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
            $table->index('product_id');
            $table->index('user_id');
            $table->index('role');
        });
    }

    public function down(): void
    {
        // Step 6: Drop product_assignments table
        Schema::dropIfExists('product_assignments');

        // Step 5: Restore settings table original constraint
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key', 'product_id']);
            $table->unique('key');
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });

        // Step 4: Remove identifier from license_activations
        Schema::table('license_activations', function (Blueprint $table) {
            $table->dropColumn('identifier');
        });

        // Step 3: Remove columns from products
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'activation_strategy',
                'default_max_activations',
                'latest_release_version',
                'category',
            ]);
        });

        // Step 2: Remove columns from downloads
        Schema::table('downloads', function (Blueprint $table) {
            $table->dropForeign(['product_release_id']);
            $table->dropColumn(['product_release_id', 'platform', 'status']);
        });

        // Step 1: Drop product_releases table
        Schema::dropIfExists('product_releases');
    }
};
