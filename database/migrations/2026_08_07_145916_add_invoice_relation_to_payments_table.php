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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->change();
            $table->enum('payment_type', ['invoice', 'order'])->default('invoice')->after('status');
            $table->decimal('refunded_amount', 12, 2)->default(0)->after('amount');

            $table->index('invoice_id');
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
            $table->dropIndex(['paid_at']);
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn(['payment_type', 'refunded_amount']);
            $table->foreignId('order_id')->nullable(false)->change();
        });
    }
};
