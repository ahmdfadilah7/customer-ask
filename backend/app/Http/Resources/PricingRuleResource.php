<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_category' => $this->whenLoaded('serviceCategory', fn () => [
                'code' => $this->serviceCategory->code,
                'name' => $this->serviceCategory->name,
            ]),
            'region_scope' => $this->region_scope,
            'raw_value' => $this->raw_value,
            'fee_type' => $this->fee_type,
            'calculation_basis' => $this->calculation_basis,
            'percentage_value' => $this->percentage_value,
            'fixed_amount' => $this->fixed_amount,
            'is_visible_to_client' => $this->is_visible_to_client,
            'hide_garuda_fee' => $this->hide_garuda_fee,
            'separate_ga_non_ga' => $this->separate_ga_non_ga,
        ];
    }
}
