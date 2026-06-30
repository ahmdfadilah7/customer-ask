<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch->id,
                'code' => $this->branch->code,
                'name' => $this->branch->name,
            ]),
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'corp_mode' => $this->corp_mode,
            'faktur_pajak' => $this->faktur_pajak,
            'show_service_fee' => $this->show_service_fee,
            'invoice_method' => $this->invoice_method,
            'cn_percentage' => $this->cn_percentage,
            'contract_period' => $this->contract_period,
            'general_note' => $this->general_note,
            'employees_count' => $this->whenCounted('employees'),
            'pic_employees_count' => $this->whenCounted('pic_employees_count'),
            'pricing_rules_count' => $this->whenCounted('pricingRules'),
            'active_pricing_rules_count' => $this->when(
                isset($this->active_pricing_rules_count),
                $this->active_pricing_rules_count
            ),
            'aliases' => $this->whenLoaded('aliases', fn () => $this->aliases->pluck('alias_name')),
            'contacts' => $this->whenLoaded('contacts', fn () => CustomerContactResource::collection($this->contacts)),
            'contacts_count' => $this->whenCounted('contacts'),
            'employees' => $this->whenLoaded('employees', fn () => EmployeeResource::collection($this->employees)),
            'active_pricing_version' => $this->when(isset($this->active_pricing_version), $this->active_pricing_version),
            'pricing' => $this->when(isset($this->pricing_groups), $this->pricing_groups),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
