<?php

namespace Paymenter\Extensions\Others\VatManager\Listeners;

use App\Events\Invoice\Finalized;
use App\Events\Invoice\Updated;
use App\Models\Invoice;
use App\Models\Service;
use Paymenter\Extensions\Others\VatManager\Models\VatProductSetting;

class ApplyVatToInvoice
{
    public function handle(Finalized|Updated $event): void
    {
        $invoice = $event->invoice;

        if ($invoice->status !== Invoice::STATUS_PENDING) {
            return;
        }

        $invoice->loadMissing('items.reference');

        $existingVat = $invoice->items()->where('reference_type', self::class)->first();
        if ($existingVat) {
            $existingVat->delete();
        }

        if (!(bool) config('settings.extension_others_vatmanager_enabled', false)) {
            return;
        }

        $rate = (float) config('settings.extension_others_vatmanager_vat_rate', 14);
        $vatAmount = 0.0;

        foreach ($invoice->items as $item) {
            if ($item->reference_type === self::class) {
                continue;
            }

            $applyVat = true;
            if ($item->reference_type === Service::class && $item->reference) {
                $productId = $item->reference->product_id;
                $productSetting = VatProductSetting::where('product_id', $productId)->first();
                $applyVat = $productSetting?->enabled ?? true;
            }

            if (!$applyVat) {
                continue;
            }

            $vatAmount += ((float) $item->price * (int) $item->quantity) * ($rate / 100);
        }

        if ($vatAmount <= 0) {
            return;
        }

        $priceDisplay = config('settings.extension_others_vatmanager_price_display', 'exclusive');
        $description = sprintf('VAT (%.2f%%)%s', $rate, $priceDisplay === 'inclusive' ? ' - Included in displayed pricing' : '');

        $invoice->items()->create([
            'price' => round($vatAmount, 2),
            'quantity' => 1,
            'description' => $description,
            'reference_id' => $invoice->id,
            'reference_type' => self::class,
        ]);
    }
}
