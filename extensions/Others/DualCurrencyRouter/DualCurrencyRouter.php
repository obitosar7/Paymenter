<?php

namespace Paymenter\Extensions\Others\DualCurrencyRouter;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Currency;
use App\Models\Gateway;
use Illuminate\Database\Eloquent\Builder;
use Paymenter\Extensions\Others\DualCurrencyRouter\Support\CurrencyContextResolver;

#[ExtensionMeta(
    name: 'Dual Currency Router',
    description: 'Display prices in one currency and route payment gateways using a different processing currency when needed.',
    version: '1.0.0',
    author: 'Paymenter',
    url: 'https://paymenter.org',
)]
class DualCurrencyRouter extends Extension
{
    public function __construct(public $config = []) {}

    public function getConfig($values = [])
    {
        $gateways = Gateway::query()->orderBy('name')->get();
        $gatewayOptions = $gateways
            ->mapWithKeys(fn (Gateway $gateway) => [$gateway->id => $gateway->name])
            ->toArray();

        $currencies = Currency::query()->orderBy('code')->get();
        $currencyOptions = $currencies
            ->mapWithKeys(fn (Currency $currency) => [$currency->code => $currency->code])
            ->toArray();

        $currencyFields = $currencies
            ->flatMap(function (Currency $currency) use ($gatewayOptions, $currencyOptions) {
                $code = strtoupper($currency->code);

                return [[
                    'name' => $this->processingCurrencySettingKey($code),
                    'label' => "$code Payment processing currency",
                    'type' => 'select',
                    'options' => $currencyOptions,
                    'description' => "Checkout can still show $code, but gateway validation/filtering will use this selected processing currency.",
                ], [
                    'name' => $this->processingRateSettingKey($code),
                    'label' => "$code -> Processing exchange rate",
                    'type' => 'text',
                    'placeholder' => 'e.g. 50.25',
                    'description' => "Multiplier used to convert $code amounts before sending payment requests to processing gateways.",
                ], [
                    'name' => $this->gatewaySettingKey($code),
                    'label' => "$code Allowed gateways",
                    'type' => 'select',
                    'multiple' => true,
                    'options' => $gatewayOptions,
                    'database_type' => 'array',
                    'description' => "Select gateways allowed for $code after processing-currency mapping. Leave empty to allow all gateways.",
                ]];
            })
            ->values()
            ->toArray();

        return array_merge([
            [
                'name' => 'gateway_visibility_mode',
                'label' => 'Gateway visibility mode',
                'type' => 'select',
                'options' => [
                    'strict_filter' => 'Only show configured gateways for the resolved processing currency',
                    'prefer_configured' => 'Show all gateways but prioritize configured gateways first',
                ],
                'default' => 'strict_filter',
                'description' => 'Strict mode is safest for avoiding unsupported currency-gateway combinations.',
            ],
        ], $currencyFields);
    }

    public function boot()
    {
        ExtensionHelper::registerMiddleware(Middleware\CaptureDisplayCurrency::class);

        $resolver = new CurrencyContextResolver($this->config);
        $allowedGatewaysByCurrency = $resolver->allowedGatewaysByProcessingCurrency();

        Gateway::addGlobalScope('dual_currency_router', function (Builder $builder) use ($resolver, $allowedGatewaysByCurrency) {
            if (!$resolver->shouldApplyScope()) {
                return;
            }

            $processingCurrency = $resolver->resolveProcessingCurrency();
            if (!$processingCurrency || !array_key_exists($processingCurrency, $allowedGatewaysByCurrency)) {
                return;
            }

            $allowedIds = $allowedGatewaysByCurrency[$processingCurrency];
            if ($allowedIds === []) {
                return;
            }

            if ($resolver->visibilityMode() === 'prefer_configured') {
                $placeholders = implode(',', array_fill(0, count($allowedIds), '?'));
                $builder->orderByRaw("CASE WHEN id IN ($placeholders) THEN 0 ELSE 1 END", $allowedIds);

                return;
            }

            $builder->whereIn('id', $allowedIds);
        });
    }

    private function gatewaySettingKey(string $currencyCode): string
    {
        return 'processing_currency_' . strtolower($currencyCode) . '_gateways';
    }

    private function processingCurrencySettingKey(string $currencyCode): string
    {
        return 'display_currency_' . strtolower($currencyCode) . '_processing_currency';
    }

    private function processingRateSettingKey(string $currencyCode): string
    {
        return 'display_currency_' . strtolower($currencyCode) . '_processing_rate';
    }
}
