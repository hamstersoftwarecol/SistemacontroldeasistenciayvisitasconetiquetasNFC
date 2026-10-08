<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->dateTime('check_in_at');
            $table->dateTime('check_out_at')->nullable();
            $table->foreignId('check_in_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('check_out_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedInteger('scans_count')->default(0);
            $table->boolean('is_late')->default(false);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index('date');
        });

        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('scanned_at');
            $table->string('type', 20)->default('visit');
            $table->string('source', 20)->default('nfc');
            $table->text('comment')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('tag_uid', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scanned_at']);
            $table->index(['location_id', 'scanned_at']);
            $table->index('scanned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
        Schema::dropIfExists('attendances');
    }
};
