<?php

// database/migrations/xxxx_xx_xx_create_companies_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Facebook
            $table->string('fb_pixel_id');
            $table->text('fb_access_token');

            // Bitrix
            $table->text('bitrix_webhook_url');
            $table->string('bitrix_inbound_token')->unique();

            // Outbound token for link identification
            $table->string('outbound_token')->unique();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
