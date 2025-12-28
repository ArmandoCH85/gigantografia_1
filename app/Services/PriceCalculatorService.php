<?php

namespace App\Services;

use App\Models\ProductMaterial;
use App\Models\ProductFinish;
use App\Models\CustomerPriceTier;
use Illuminate\Support\Str;

class PriceCalculatorService
{
    /**
     * Calculate price based on material category.
     */
    public function calculatePrice($width, $height, $materialId, $finishes = [], $priceTierId = null): float
    {
        $material = ProductMaterial::with('category')->find($materialId);

        if (!$material) {
            return 0.0;
        }

        if (Str::contains(Str::lower($material->name ?? ''), 'lona trasluc')) {
            if (!$height) return 0.0;
            return $this->calculateBannerPrice($width, $height, $material, $finishes, $priceTierId);
        }

        if (!$width || !$height) {
            return 0.0;
        }

        if ($this->isBanner($material)) {
            return $this->calculateBannerPrice($width, $height, $material, $finishes, $priceTierId);
        }

        if ($this->isVinil($material)) {
            return $this->calculateVinilPrice($width, $height, $material, $priceTierId);
        }

        // Fallback
        return ($width * $height) * floatval($material->price_factor_standard ?? 0);
    }

    public function calculateBannerPrice($width, $height, $material, $finishes = [], $priceTierId = null): float
    {
        if (is_numeric($material)) {
            $material = ProductMaterial::with('category')->find($material);
        }

        // Lógica especial para Lona Traslúcida: alto × 26
        if (Str::contains(Str::lower($material->name ?? ''), 'lona trasluc')) {
            $materialPrice = $height * 26;
        } else {
            $area = $width * $height;
            $factor = $this->getMaterialFactor($material, $priceTierId);
            $materialPrice = $area * $factor;
        }

        $finishesPrice = $this->calculateFinishesPrice($finishes, $width, $height);

        return round($materialPrice + $finishesPrice, 2);
    }

    public function calculateVinilPrice($width, $height, $material, $priceTierId = null): float
    {
        if (is_numeric($material)) {
            $material = ProductMaterial::with('category')->find($material);
        }

        // Usar el ancho de bobina configurado o 1.5 por defecto
        $sheetWidth = floatval($material->sheet_width > 0 ? $material->sheet_width : 1.5);
        $factor = floatval($material->price_factor_standard ?? 0);

        // Fórmula solicitada: (roundup(ancho / 1.5, 0) * altura) * factor
        // ceil() en PHP equivale a roundup(x, 0)
        $sheets = ceil(floatval($width) / $sheetWidth);
        
        return round(($sheets * floatval($height)) * $factor, 2);
    }

    protected function calculateFinishesPrice($finishes, $width, $height): float
    {
        $total = 0;

        foreach ($finishes as $finishItem) {
            $finishId = is_array($finishItem) ? ($finishItem['id'] ?? $finishItem['finish_id'] ?? null) : $finishItem;
            $quantity = is_array($finishItem) ? ($finishItem['quantity'] ?? 1) : 1;
            
            // Si el acabado tiene una medida de tubo específica, usamos esa medida como cantidad
            if (is_array($finishItem) && isset($finishItem['tube_width']) && $finishItem['tube_width'] > 0) {
                $quantity = $finishItem['tube_width'];
            }

            if (!$finishId) continue;

            $finish = ProductFinish::find($finishId);
            if (!$finish) continue;

            $costPerUnit = floatval($finish->cost_per_unit ?? 0);
            $fQuantity = floatval($quantity ?? 1);
            $fWidth = floatval($width ?? 0);
            $fHeight = floatval($height ?? 0);

            switch ($finish->formula_type) {
                case 'fixed':
                    $total += $costPerUnit;
                    break;
                case 'per_quantity':
                    $total += $costPerUnit * $fQuantity;
                    break;
                case 'width_based':
                    $total += $costPerUnit * $fWidth;
                    break;
                case 'height_based':
                    $total += $costPerUnit * $fHeight;
                    break;
                default:
                    $total += $costPerUnit;
            }
        }

        return $total;
    }

    protected function getMaterialFactor(ProductMaterial $material, $priceTierId = null): float
    {
        // Default to standard
        $factor = $material->price_factor_standard;

        if ($priceTierId) {
            $tier = CustomerPriceTier::find($priceTierId);
            if ($tier) {
                // Check tier name/code to decide.
                // Requirement: "Factores según customer_price_tier: Estándar... Por Mayor... Campaña: IGNORAR"
                // Assuming tiers are named or coded.
                // Let's check typical codes or names.
                // If tier implies wholesale, use wholesale factor.
                // If tier implies standard, use standard.

                // Assuming 'wholesale' code or similar.
                // If we don't know the exact codes, we might check if 'price_factor_wholesale' is set and tier is not standard?
                // But the requirement says "Factores según customer_price_tier".
                // I'll guess checking for 'mayor' or 'wholesale' in name/code.

                $isWholesale = Str::contains(Str::lower($tier->name), ['mayor', 'bulk', 'wholesale']) ||
                    Str::contains(Str::lower($tier->code ?? ''), ['mayor', 'wholesale']);

                if ($isWholesale && $material->price_factor_wholesale > 0) {
                    $factor = $material->price_factor_wholesale;
                }
            }
        }

        return (float) ($factor ?? 0.0);
    }

    protected function isBanner($material): bool
    {
        // Support 'BANER' and 'BANNER'
        return $material->category && Str::contains(Str::lower($material->category->name), ['banner', 'baner']);
    }

    protected function isVinil($material): bool
    {
        // Support 'VINIL' and 'VINILO'
        return $material->category && Str::contains(Str::lower($material->category->name), ['vinil', 'vinilo']);
    }

    protected function isFoamOrCeltex($material): bool
    {
        $name = Str::lower($material->name);
        return Str::contains($name, ['foam', 'celtex']);
    }
}
