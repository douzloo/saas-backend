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
            $table->string('authority', 100)->nullable()->after('transaction_id');
            $table->string('ref_id', 100)->nullable()->after('authority');
            $table->timestamp('verified_at')->nullable()->after('paid_at');
            $table->timestamp('failed_at')->nullable()->after('verified_at');

            $table->index('authority');
            $table->index('ref_id');
            $table->index('verified_at');
            $table->index(['gateway', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['gateway', 'status']);
            $table->dropIndex(['verified_at']);
            $table->dropIndex(['ref_id']);
            $table->dropIndex(['authority']);

            $table->dropColumn(['authority', 'ref_id', 'verified_at', 'failed_at']);
        });
    }
};
