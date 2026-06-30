<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'branch_id', 'code', 'name', 'slug', 'customer_group_id',
        'corp_mode', 'handler', 'faktur_pajak', 'show_service_fee',
        'invoice_method', 'cn_percentage', 'invoice_per_person',
        'kick_off_date', 'contract_end_date', 'contract_period', 'status', 'general_note',
    ];

    protected function casts(): array
    {
        return [
            'corp_mode' => 'boolean',
            'faktur_pajak' => 'boolean',
            'show_service_fee' => 'boolean',
            'invoice_per_person' => 'boolean',
            'cn_percentage' => 'decimal:2',
            'kick_off_date' => 'date',
            'contract_end_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(CustomerAlias::class);
    }

    public function entities(): HasMany
    {
        return $this->hasMany(CustomerEntity::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function airlineCodes(): HasMany
    {
        return $this->hasMany(CustomerAirlineCode::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(CustomerPricingRule::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Customer $customer) {
            if ($customer->isForceDeleting()) {
                return;
            }

            $customer->employees()->each(fn (Employee $employee) => $employee->delete());
            $customer->contacts()->delete();
        });

        static::restoring(function (Customer $customer) {
            Employee::onlyTrashed()
                ->where('customer_id', $customer->id)
                ->each(fn (Employee $employee) => $employee->restore());
        });

        static::forceDeleting(function (Customer $customer) {
            $customer->employees()->withTrashed()->get()->each->forceDelete();
            $customer->contacts()->delete();
            $customer->pricingRules()->delete();
            $customer->aliases()->delete();
            $customer->entities()->delete();
            $customer->airlineCodes()->delete();
            $customer->notes()->delete();
        });
    }
}
