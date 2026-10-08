<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_email')->nullable();
            $table->string('reporter_phone', 50)->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 50)->default('general');
            $table->string('priority', 20)->default('medium');
            $table->string('subject');
            $table->text('description');
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('attachment_path')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('complaint_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('status_from', 20)->nullable();
            $table->string('status_to', 20)->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_updates');
        Schema::dropIfExists('complaints');
    }
};
