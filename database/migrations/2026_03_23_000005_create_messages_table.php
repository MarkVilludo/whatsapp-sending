<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_number_id')->constrained('phone_numbers');
            $table->foreignId('to_number_id')->constrained('phone_numbers');
            $table->foreignId('provider_id')->constrained('providers');
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->text('message_text');
            $table->enum('status', ['sent', 'delivered', 'received', 'failed'])->default('sent');
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
