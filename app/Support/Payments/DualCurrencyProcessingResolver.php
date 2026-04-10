<?php

namespace App\Support\Payments;

use App\Models\Extension;
use App\Models\Invoice;

class DualCurrencyProcessingResolver
{
    public function forGatewaySelection(string $currency): string
    {
        $context = $this->resolve($currency, null);

        return $context['processing_currency'];
    }

    public function forInvoicePayment(Invoice $invoice, float $amount): array
    {
        return $this->resolve($invoice->currency_code, $amount);
    }

    public function forCurrencyAndAmount(string $currency, float $amount): array
    {
        return $this->resolve($currency, $amount);
    }

    private function resolve(string $displayCurrency, ?float $displayAmount): array
    {
        $displayCurrency = strtoupper($displayCurrency);
        $settings = $this->settings();

        $processingCurrency = strtoupper((string) ($settings[$this->processingCurrencyKey($displayCurrency)] ?? $displayCurrency));
        if ($processingCurrency === '') {
            $processingCurrency = $displayCurrency;
        }

        $rate = 1.0;
        if ($processingCurrency !== $displayCurrency) {
            $configuredRate = $settings[$this->processingRateKey($displayCurrency)] ?? null;
            if (is_numeric($configuredRate) && (float) $configuredRate > 0) {
                $rate = (float) $configuredRate;
            }
        }

        $processingAmount = $displayAmount;
        if ($displayAmount !== null && $processingCurrency !== $displayCurrency) {
            $processingAmount = round($displayAmount * $rate, 2);
        }

        return [
            'display_currency' => $displayCurrency,
            'processing_currency' => $processingCurrency,
            'rate' => $rate,
            'display_amount' => $displayAmount,
            'processing_amount' => $processingAmount,
        ];
    }

    private function settings(): array
    {
        $extension = Extension::query()
            ->where('type', 'other')
            ->where('extension', 'DualCurrencyRouter')
            ->where('enabled', true)
            ->first();

        if (!$extension) {
            return [];
        }

        return $extension->settings->pluck('value', 'key')->toArray();
    }

    private function processingCurrencyKey(string $displayCurrency): string
    {
        return 'display_currency_' . strtolower($displayCurrency) . '_processing_currency';
    }

    private function processingRateKey(string $displayCurrency): string
    {
        return 'display_currency_' . strtolower($displayCurrency) . '_processing_rate';
    }
}
