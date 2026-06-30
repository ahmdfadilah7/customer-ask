<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPricingRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_category' => $this->whenLoaded('serviceCategory', fn () => [
                'code' => $this->serviceCategory->code,
                'name' => $this->serviceCategory->name,
                'group_code' => $this->serviceCategory->group_code,
            ]),
            'region_scope' => $this->whenLoaded('regionScope', fn () => [
                'code' => $this->regionScope->code,
                'name' => $this->regionScope->name,
            ]),
            'airline' => $this->whenLoaded('airline', fn () => $this->airline ? [
                'code' => $this->airline->code,
                'name' => $this->airline->name,
            ] : null),
            'source_row' => $this->source_row,
            'raw_value' => $this->raw_value,
            'fee_type' => $this->fee_type,
            'calculation_basis' => $this->calculation_basis,
            'percentage_value' => $this->percentage_value,
            'fixed_amount' => $this->fixed_amount,
        ];
    }
}
