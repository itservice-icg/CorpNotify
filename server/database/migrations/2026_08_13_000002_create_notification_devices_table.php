<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'device_id']);
            $table->index(['device_id', 'acknowledged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_devices');
    }
};
