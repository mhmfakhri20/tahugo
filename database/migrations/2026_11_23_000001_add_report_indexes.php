<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the aggregate report queries (F6, REP-1..6).
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['location_id', 'status', 'created_at'], 'sales_location_status_created_index');
            $table->index(['status', 'created_at'], 'sales_status_created_index');
        });

        Schema::table('cash_entries', function (Blueprint $table) {
            $table->index(['direction', 'status', 'created_at'], 'cash_entries_direction_status_created_index');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['type', 'created_at'], 'stock_movements_type_created_index');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->index('closed_at');
        });

        Schema::table('stock_requests', function (Blueprint $table) {
            $table->index('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_location_status_created_index');
            $table->dropIndex('sales_status_created_index');
        });

        Schema::table('cash_entries', function (Blueprint $table) {
            $table->dropIndex('cash_entries_direction_status_created_index');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_type_created_index');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropIndex(['closed_at']);
        });

        Schema::table('stock_requests', function (Blueprint $table) {
            $table->dropIndex(['shipped_at']);
        });
    }
};
