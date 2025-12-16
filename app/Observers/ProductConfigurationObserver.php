<?php

namespace App\Observers;

use App\Models\ProductConfiguration;
use App\Models\QuotationDetail;
use App\Services\PriceCalculatorService;
use Illuminate\Support\Facades\Log;

class ProductConfigurationObserver
{
    protected $calculator;

    public function __construct(PriceCalculatorService $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Handle the ProductConfiguration "saving" event.
     */
    public function saving(ProductConfiguration $config): void
    {
        try {
            // Only calculate if we have dimensions and material
            if (!$config->width || !$config->height || !$config->material_id) {
                return;
            }

            // Get Price Tier from related model
            $priceTierId = null;
            if ($config->reference_type === QuotationDetail::class && $config->reference_id) {
                $detail = QuotationDetail::with('quotation.customer')->find($config->reference_id);
                if ($detail && $detail->quotation && $detail->quotation->customer) {
                    $priceTierId = $detail->quotation->customer->price_tier_id;
                }
            }
            // Add other reference types logic if needed (e.g. OrderDetail)

            $price = $this->calculator->calculatePrice(
                $config->width,
                $config->height,
                $config->material_id,
                $config->finishes ?? [],
                $priceTierId
            );

            $config->calculated_price = $price;
        } catch (\Exception $e) {
            Log::error('Error calculating price in ProductConfigurationObserver: ' . $e->getMessage());
        }
    }

    public function creating(ProductConfiguration $config): void
    {
        $this->saving($config);
    }

    public function updating(ProductConfiguration $config): void
    {
        $this->saving($config);
    }
}
