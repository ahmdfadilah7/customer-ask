<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'title_id' => $this->title_id,
            'nationality_id' => $this->nationality_id,
            'full_name' => $this->full_name,
            'title' => $this->whenLoaded('title', fn () => $this->title?->name),
            'nationality' => $this->whenLoaded('nationality', fn () => $this->nationality?->name),
            'passport_number' => $this->passport_number,
            'passport_expiry' => $this->passport_expiry?->toDateString(),
            'ktp_number' => $this->ktp_number,
            'birthdate' => $this->birthdate?->toDateString(),
            'mobile' => $this->mobile,
            'email' => $this->email,
            'ticket_name_format' => $this->ticket_name_format,
            'status' => $this->status,
            'is_pic' => $this->when(
                $this->relationLoaded('contact'),
                fn () => $this->contact !== null
            ),
            'is_primary_pic' => $this->when(
                $this->relationLoaded('contact'),
                fn () => (bool) $this->contact?->is_primary
            ),
            'contact' => $this->when(
                $this->relationLoaded('contact') && $this->contact,
                fn () => [
                    'id' => $this->contact->id,
                    'name' => $this->contact->name,
                    'phone' => $this->contact->phone,
                    'email' => $this->contact->email,
                    'is_primary' => (bool) $this->contact->is_primary,
                ]
            ),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'branch' => $this->customer->relationLoaded('branch') && $this->customer->branch ? [
                    'id' => $this->customer->branch->id,
                    'code' => $this->customer->branch->code,
                    'name' => $this->customer->branch->name,
                ] : null,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
