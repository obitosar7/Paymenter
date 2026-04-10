<?php

namespace Paymenter\Extensions\Others\DualCurrencyRouter\Support;

use Illuminate\Support\Facades\Request;

class CurrencyContextResolver
{
    public function __construct(private readonly array $config) {}

    public function resolveProcessingCurrency(): ?string
    {
        $displayCurrency = $this->resolveDisplayCurrency();
        if (!$displayCurrency) {
            return null;
        }

        $mappedCurrency = $this->config[$this->processingCurrencySettingKey($displayCurrency)] ?? null;

        if (!is_string($mappedCurrency) || $mappedCurrency === '') {
            return $displayCurrency;
        }

        return strtoupper($mappedCurrency);
    }

    public function allowedGatewaysByProcessingCurrency(): array
    {
        $rules = [];

        foreach ($this->config as $key => $value) {
            if (!str_starts_with($key, 'processing_currency_') || !str_ends_with($key, '_gateways')) {
                continue;
            }

            $currency = strtoupper(substr($key, strlen('processing_currency_'), -strlen('_gateways')));

            if (!is_array($value)) {
                continue;
            }

            $gatewayIds = array_values(array_filter($value, fn ($id) => is_numeric($id)));
            if ($gatewayIds === []) {
                continue;
            }

            $rules[$currency] = array_map('intval', $gatewayIds);
        }

        return $rules;
    }

    public function visibilityMode(): string
    {
        $mode = $this->config['gateway_visibility_mode'] ?? 'strict_filter';

        return in_array($mode, ['strict_filter', 'prefer_configured'], true)
            ? $mode
            : 'strict_filter';
    }

    public function shouldApplyScope(): bool
    {
        if (app()->runningInConsole()) {
            return false;
        }

        $path = Request::path();
        if (
            str_starts_with($path, 'admin')
            || str_starts_with($path, 'api/v1/admin')
            || str_starts_with($path, 'extensions/')
            || str_starts_with($path, 'api/v1/extensions/')
        ) {
            return false;
        }

        $referer = Request::header('referer');
        if ($referer) {
            $refererPath = parse_url($referer, PHP_URL_PATH) ?? '';
            if (str_starts_with(ltrim($refererPath, '/'), 'admin')) {
                return false;
            }
        }

        return true;
    }

    private function resolveDisplayCurrency(): ?string
    {
        $currency = request()->attributes->get('dual_currency.display_currency')
            ?? session('dual_currency.display_currency')
            ?? session('currency')
            ?? config('settings.default_currency');

        if (!is_string($currency) || $currency === '') {
            return null;
        }

        return strtoupper($currency);
    }

    private function processingCurrencySettingKey(string $displayCurrencyCode): string
    {
        return 'display_currency_' . strtolower($displayCurrencyCode) . '_processing_currency';
    }
}
