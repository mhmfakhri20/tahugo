<?php

use App\Enums\CashDirection;
use App\Enums\ExpenseStatus;
use App\Enums\FundSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cash flow (PRD §6.5, §9): outlet expenses, other income, business expenses, purchases (CASH-7).
     * Money in integer rupiah (NFR-6).
     */
    public function up(): void
    {
        Schema::create('cash_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('direction', CashDirection::values());
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('description', 500);
            $table->enum('fund_source', FundSource::values())->nullable();
            $table->string('receipt_photo_path')->nullable();
            $table->enum('status', ExpenseStatus::values())->default(ExpenseStatus::Pending->value);
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->nullableMorphs('source');
            $table->timestamps();

            $table->index(['shift_id', 'direction', 'status']);
            $table->index(['location_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_entries');
    }
};
