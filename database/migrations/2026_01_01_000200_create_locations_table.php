<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('area')->nullable();
            $table->string('floor', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('token', 64)->unique();
            $table->string('public_token', 64)->unique();
            $table->string('tag_uid', 100)->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('written_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
