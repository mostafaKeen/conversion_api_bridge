<?php

// app/Models/Event.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'entity_type',
        'entity_id',
        'event_name',
        'crm_stage',
        'user_data',
        'custom_data',
        'sent_to_facebook',
        'sent_at',
    ];

    protected $casts = [
        'user_data' => 'array',
        'custom_data' => 'array',
        'sent_to_facebook' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
