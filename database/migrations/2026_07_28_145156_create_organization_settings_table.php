<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_settings', function (Blueprint $table) {

            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('company_name');
            $table->string('brand_name')->nullable();

            $table->string('logo')->nullable();

            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            $table->text('address')->nullable();

            $table->string('currency', 10)
                ->default('IRR');

            $table->string('timezone')
                ->default('Asia/Tehran');

            $table->string('locale')
                ->default('fa');

            $table->string('fiscal_year_start')
                ->default('01-01');

            $table->unsignedInteger('invoice_sequence')
                ->default(1);

            $table->decimal('tax_percent', 8, 2)
                ->default(0);

            $table->json('settings')
                ->nullable();

            $table->timestamps();

            $table->unique('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
