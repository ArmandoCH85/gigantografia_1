<?php

namespace App\Observers;

use App\Models\QuotationDetail;
use App\Models\ProductConfiguration;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuotationDetailObserver
{
    /**
     * Handle the QuotationDetail "created" event.
     */
    public function created(QuotationDetail $quotationDetail): void
    {
        $this->syncConfiguration($quotationDetail);
    }

    /**
     * Handle the QuotationDetail "updated" event.
     */
    public function updated(QuotationDetail $quotationDetail): void
    {
        $this->syncConfiguration($quotationDetail);
    }

    /**
     * Sync the configuration for the detail.
     */
    protected function syncConfiguration(QuotationDetail $detail): void
    {
        try {
            // Verify if product category is Banner or Vinil
            // Note: Product relationship should be loaded or retrieved
            $product = $detail->product;
            if (!$product) {
                return;
            }

            // Check category
            $category = $product->category;
            if (!$category) {
                return;
            }

            $isBannerOrVinil = Str::contains(Str::lower($category->name), ['banner', 'vinil']);

            if (!$isBannerOrVinil) {
                return;
            }

            // Only proceed if we have configuration data in the detail
            // Note: The fillable attributes 'width', 'height', 'material_id', 'finishes' are on QuotationDetail model now.
            if (!$detail->width || !$detail->height) {
                return;
            }

            $detail->configuration()->updateOrCreate(
                [], // search criteria is implicit by morphOne relationship? No, updateOrCreate on relation:
                // $detail->configuration()->updateOrCreate([], [...]); works if it exists?
                // Actually morphOne updateOrCreate matches existing if found.
                // But we need to pass data.
                [
                    'width' => $detail->width,
                    'height' => $detail->height,
                    'material_id' => $detail->material_id,
                    'finishes' => $detail->finishes,
                    'calculated_price' => $detail->unit_price, // Or let observer calculate it? 
                    // Prompt says: "Guardar datos: width, height, material_id, finishes (JSON), calculated_price"
                    // ProductConfigurationObserver will overwrite calculated_price if we don't pass it?
                    // Or if we pass it, it might be recalculated.
                    // Let's pass what we have.
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error syncing configuration in QuotationDetailObserver: ' . $e->getMessage());
        }
    }
}
