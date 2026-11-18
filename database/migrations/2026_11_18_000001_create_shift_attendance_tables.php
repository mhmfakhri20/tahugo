<?php

use App\Enums\AttendanceType;
use App\Enums\ShiftStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shifts & attendance (PRD §6.3, §9). Money in integer rupiah (NFR-6).
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('opened_at');
            $table->unsignedBigInteger('opening_cash');
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('counted_cash')->nullable();
            $table->bigInteger('expected_cash')->nullable();
            $table->bigInteger('variance')->nullable();
            $table->string('closing_photo_path')->nullable();
            $table->enum('status', ShiftStatus::values())->default(ShiftStatus::Open->value);
            $table->timestamps();

            $table->index(['location_id', 'status']);
            $table->index(['location_id', 'opened_at']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', AttendanceType::values());
            $table->dateTime('server_time');
            $table->string('photo_path')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_m', 8, 1);
            $table->decimal('distance_m', 10, 1);
            $table->json('flags')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'server_time']);
            $table->index(['location_id', 'server_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('shifts');
    }
};
