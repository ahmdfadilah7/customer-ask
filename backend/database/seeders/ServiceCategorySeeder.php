<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceCategorySeeder extends Seeder
{
    /**
     * Format master kategori layanan — dipakai semua cabang.
     * Satu sel pricing = service_category + region_scope + airline (opsional).
     */
    public function run(): void
    {
        $now = now();

        $categories = [
            // Grup header (bukan slot pricing)
            ['code' => 'GRP_TICKETING', 'name' => 'Ticketing', 'group_code' => 'TICKETING', 'parent_code' => null, 'requires_scope' => false, 'requires_airline' => false, 'is_pricing_slot' => false, 'sort_order' => 1],
            ['code' => 'GRP_ACCOMMODATION', 'name' => 'Akomodasi', 'group_code' => 'ACCOMMODATION', 'parent_code' => null, 'requires_scope' => false, 'requires_airline' => false, 'is_pricing_slot' => false, 'sort_order' => 2],
            ['code' => 'GRP_GROUND', 'name' => 'Transport Darat', 'group_code' => 'GROUND', 'parent_code' => null, 'requires_scope' => false, 'requires_airline' => false, 'is_pricing_slot' => false, 'sort_order' => 3],
            ['code' => 'GRP_ADMIN', 'name' => 'Administrasi', 'group_code' => 'ADMIN', 'parent_code' => null, 'requires_scope' => false, 'requires_airline' => false, 'is_pricing_slot' => false, 'sort_order' => 4],
            ['code' => 'GRP_OTHER', 'name' => 'Lain-lain', 'group_code' => 'OTHER', 'parent_code' => null, 'requires_scope' => false, 'requires_airline' => false, 'is_pricing_slot' => false, 'sort_order' => 5],

            // Slot pricing — ticketing
            ['code' => 'AIRLINE', 'name' => 'Tiket Pesawat', 'group_code' => 'TICKETING', 'parent_code' => 'GRP_TICKETING', 'requires_scope' => true, 'requires_airline' => true, 'is_pricing_slot' => true, 'sort_order' => 1, 'description' => 'Service fee / markup tiket. Pilih scope wilayah + maskapai/grup maskapai.'],
            ['code' => 'ISSUE_24JAM', 'name' => 'Issue 24 Jam', 'group_code' => 'TICKETING', 'parent_code' => 'GRP_TICKETING', 'requires_scope' => false, 'requires_airline' => false, 'is_pricing_slot' => true, 'sort_order' => 2],
            ['code' => 'REISSUE_FEE', 'name' => 'Biaya Reissue', 'group_code' => 'TICKETING', 'parent_code' => 'GRP_TICKETING', 'requires_scope' => true, 'requires_airline' => false, 'is_pricing_slot' => true, 'sort_order' => 3],
            ['code' => 'REFUND', 'name' => 'Refund', 'group_code' => 'TICKETING', 'parent_code' => 'GRP_TICKETING', 'requires_scope' => true, 'requires_airline' => false, 'is_pricing_slot' => true, 'sort_order' => 4],

            // Slot pricing — akomodasi
            ['code' => 'HOTEL', 'name' => 'Hotel', 'group_code' => 'ACCOMMODATION', 'parent_code' => 'GRP_ACCOMMODATION', 'requires_scope' => true, 'requires_airline' => false, 'is_pricing_slot' => true, 'sort_order' => 1],

            // Slot pricing — darat
            ['code' => 'TRAIN_BUS_TRAVEL', 'name' => 'Kereta / Bus / Travel', 'group_code' => 'GROUND', 'parent_code' => 'GRP_GROUND', 'requires_scope' => true, 'requires_airline' => false, 'is_pricing_slot' => true, 'sort_order' => 1],
            ['code' => 'RENT_CAR', 'name' => 'Sewa Mobil', 'group_code' => 'GROUND', 'parent_code' => 'GRP_GROUND', 'requires_scope' => true, 'requires_airline' => false, 'is_pricing_slot' => true, 'sort_order' => 2],

            // Slot pricing — administrasi
            ['code' => 'DOC_VISA', 'name' => 'Dokumen / Visa', 'group_code' => 'ADMIN', 'parent_code' => 'GRP_ADMIN', 'requires_scope' => false, 'requires_airline' => false, 'is_pricing_slot' => true, 'sort_order' => 1],
            ['code' => 'MATERAI', 'name' => 'Bea Materai', 'group_code' => 'ADMIN', 'parent_code' => 'GRP_ADMIN', 'requires_scope' => false, 'requires_airline' => false, 'is_pricing_slot' => true, 'sort_order' => 2],
            ['code' => 'TAKEOVER_PAYMENT', 'name' => 'Takeover Payment', 'group_code' => 'ADMIN', 'parent_code' => 'GRP_ADMIN', 'requires_scope' => false, 'requires_airline' => false, 'is_pricing_slot' => true, 'sort_order' => 3],

            // Slot pricing — lain-lain
            ['code' => 'INSURANCE', 'name' => 'Asuransi', 'group_code' => 'OTHER', 'parent_code' => 'GRP_OTHER', 'requires_scope' => false, 'requires_airline' => false, 'is_pricing_slot' => true, 'sort_order' => 1],
            ['code' => 'OTHERS', 'name' => 'Lain-lain', 'group_code' => 'OTHER', 'parent_code' => 'GRP_OTHER', 'requires_scope' => false, 'requires_airline' => false, 'is_pricing_slot' => true, 'sort_order' => 2],
        ];

        foreach ($categories as $cat) {
            DB::table('service_categories')->updateOrInsert(
                ['code' => $cat['code']],
                [
                    'name' => $cat['name'],
                    'group_code' => $cat['group_code'],
                    'description' => $cat['description'] ?? null,
                    'requires_scope' => $cat['requires_scope'],
                    'requires_airline' => $cat['requires_airline'],
                    'is_pricing_slot' => $cat['is_pricing_slot'],
                    'sort_order' => $cat['sort_order'],
                    'status' => 'active',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        foreach ($categories as $cat) {
            $parentId = null;
            if ($cat['parent_code'] !== null) {
                $parentId = DB::table('service_categories')->where('code', $cat['parent_code'])->value('id');
            }

            DB::table('service_categories')->where('code', $cat['code'])->update([
                'parent_id' => $parentId,
                'updated_at' => $now,
            ]);
        }

        // Nonaktifkan kode lama yang diganti format master
        DB::table('service_categories')
            ->whereIn('code', [
                'AIRLINES', 'AIRLINE_INTL', 'AIRLINE_DOM',
                'AIRLINE_DOM_GA', 'AIRLINE_DOM_OTHER', 'AIRLINE_DOM_LCC', 'AIRLINE_DOM_PERINTIS',
                'REFUND_DOMESTIC', 'REFUND_INTERNATIONAL', 'TICKET_COMBINED',
            ])
            ->update(['status' => 'inactive', 'is_pricing_slot' => false, 'updated_at' => $now]);
    }
}
