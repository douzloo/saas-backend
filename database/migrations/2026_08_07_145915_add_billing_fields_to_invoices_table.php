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
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('discount', 12, 2)->default(0)->after('subtotal');
            $table->decimal('balance_due', 12, 2)->default(0)->after('total');
            $table->foreignId('organization_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'sent', 'issued', 'paid', 'overdue', 'cancelled'])->default('draft')->change();

            $table->index('due_at');
            $table->index('issued_at');
            $table->index(['user_id', 'status']);
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['due_at']);
            $table->dropIndex(['issued_at']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['organization_id']);
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['discount', 'balance_due']);
        });
    }
};
