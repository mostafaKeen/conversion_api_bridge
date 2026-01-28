<?php
// app/Models/WebhookLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'bitrix_request',  // raw webhook from Bitrix
        'fb_payload',      // what you sent to FB
        'fb_response',     // FB response
        'status',          // success / failed
        'error_message',   // optional error info
    ];

    protected $casts = [
        'bitrix_request' => 'array',
        'fb_payload'     => 'array',
        'fb_response'    => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
