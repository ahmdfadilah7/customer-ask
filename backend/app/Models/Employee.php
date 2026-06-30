<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Employee extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'customer_id', 'title_id', 'nationality_id', 'full_name',
        'passport_number', 'passport_expiry', 'ktp_number', 'birthdate',
        'mobile', 'email', 'ticket_name_format', 'status',
    ];

    protected function casts(): array
    {
        return [
            'passport_expiry' => 'date',
            'birthdate' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function nationality(): BelongsTo
    {
        return $this->belongsTo(Nationality::class);
    }

    public function contact(): HasOne
    {
        return $this->hasOne(CustomerContact::class);
    }
}
