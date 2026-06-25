<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('confirmed_at');
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->after('payment_reference');
            }

            if (! Schema::hasColumn('payments', 'request_hash')) {
                $table->string('request_hash', 64)->nullable()->after('idempotency_key');
            }

            if (! Schema::hasColumn('payments', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('payload');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY status VARCHAR(32) NOT NULL DEFAULT 'processing'");
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->unique('idempotency_key');
            $table->unique(['gateway', 'external_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropUnique(['gateway', 'external_transaction_id']);
            $table->dropColumn(['idempotency_key', 'request_hash', 'processed_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['confirmed_at', 'cancelled_at']);
        });
    }
};
