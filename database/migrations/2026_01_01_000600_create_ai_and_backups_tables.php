<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('Nueva conversación');
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->longText('content');
            $table->json('tools_used')->nullable();
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('drive_status', 20)->default('skipped');
            $table->string('drive_file_id')->nullable();
            $table->text('error')->nullable();
            $table->string('trigger', 20)->default('manual');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
