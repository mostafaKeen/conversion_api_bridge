<?php
// database/migrations/xxxx_xx_xx_create_events_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('entity_type'); // LEAD / DEAL
            $table->unsignedBigInteger('entity_id');

            $table->string('event_name');
            $table->string('crm_stage')->nullable();

            $table->json('user_data')->nullable();
            $table->json('custom_data')->nullable();

            $table->boolean('sent_to_facebook')->default(false);
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
