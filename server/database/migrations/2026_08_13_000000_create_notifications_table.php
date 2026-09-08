<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('type', 32)->default('info');
            $table->string('url', 2048)->nullable();
            $table->enum('target_type', ['all', 'department', 'device', 'user'])->default('all');
            $table->string('target_value')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('expire_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'start_at', 'expire_at']);
            $table->index(['target_type', 'target_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
