<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();

            // Optional link to the company
            $table->foreignId('company_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            // Original Bitrix request
            $table->json('bitrix_request');

            // FB payload you sent
            $table->json('fb_payload')->nullable();

            // FB response
            $table->json('fb_response')->nullable();

            // Status: success if FB accepted, failed otherwise
            $table->enum('status', ['success', 'failed'])->default('failed');

            // Optional error message
            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
