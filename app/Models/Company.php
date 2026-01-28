<?php

// app/Models/Company.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'fb_pixel_id',
        'fb_access_token',
        'bitrix_webhook_url',
        'bitrix_inbound_token',
        'outbound_token',
        'is_active',
    ];

    public function webhookLogs()
    {
        return $this->hasMany(WebhookLog::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
