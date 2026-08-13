<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->enum('status', ['active', 'inactive', 'expired', 'suspended', 'revoked'])->default('active');
            $table->enum('type', ['trial', 'standard', 'extended', 'enterprise'])->default('standard');
            $table->integer('max_activations')->default(1);
            $table->integer('activation_count')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_check_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('product_id');
            $table->index('status');
            $table->index('expires_at');
        });

        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('ip_address', 45)->nullable();
            $table->string('hostname')->nullable();
            $table->string('platform')->nullable();
            $table->string('php_version', 20)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('fingerprint', 128)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('license_id');
            $table->index('domain');
            $table->index('fingerprint');
        });

        Schema::create('license_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activation_id')->nullable()->constrained('license_activations')->nullOnDelete();
            $table->string('ip_address', 45);
            $table->string('domain')->nullable();
            $table->boolean('is_valid');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index('license_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_verifications');
        Schema::dropIfExists('license_activations');
        Schema::dropIfExists('licenses');
    }
};
