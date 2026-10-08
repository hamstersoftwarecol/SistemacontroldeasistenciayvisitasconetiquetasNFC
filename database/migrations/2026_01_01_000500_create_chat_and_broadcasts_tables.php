<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            // NULL = canal general del equipo.
            $table->foreignId('recipient_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'sender_id']);
        });

        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('audience', 20)->default('all');
            $table->string('priority', 20)->default('normal');
            $table->boolean('send_email')->default(false);
            $table->timestamps();
        });

        Schema::create('broadcast_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->unique(['broadcast_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_reads');
        Schema::dropIfExists('broadcasts');
        Schema::dropIfExists('messages');
    }
};
