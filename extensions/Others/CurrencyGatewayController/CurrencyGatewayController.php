<?php

namespace Paymenter\Extensions\Others\CurrencyGatewayController;

use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Currency;
use App\Models\Gateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;

class CurrencyGatewayController extends Extension
{
    public function __construct(public $config = []) {}

    public function getConfig($values = [])
    {
        $gateways = Gateway::query()->orderBy('name')->get();
        $gatewayOptions = $gateways
            ->mapWithKeys(fn (Gateway $gateway) => [$gateway->id => $gateway->name])
            ->toArray();

        $currencies = Currency::query()
            ->orderBy('code')
            ->get();

        $currencyOptions = $currencies
            ->mapWithKeys(fn (Currency $currency) => [$currency->code => $currency->code])
            ->toArray();

        $currencyFields = $currencies
            ->flatMap(function (Currency $currency) use ($gatewayOptions, $currencyOptions) {
                return [[
                    'name' => $this->gatewaySettingKey($currency->code),
                    'label' => "{$currency->code} Allowed Gateways",
                    'type' => 'select',
                    'multiple' => true,
                    'options' => $gatewayOptions,
                    'database_type' => 'array',
                    'description' => "Select the gateways available when paying in {$currency->code}. Leave empty to allow all gateways.",
                ], [
                    'name' => $this->gatewayCurrencySettingKey($currency->code),
                    'label' => "{$currency->code} Process payments as",
                    'type' => 'select',
                    'options' => $currencyOptions,
                    'description' => "Optional: keep checkout prices shown in {$currency->code} but resolve gateways using another currency in the background.",
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
                    'strict_filter' => 'Show only configured gateways for the selected currency',
                    'prefer_configured' => 'Show all gateways (prioritize configured gateways first)',
                ],
                'default' => 'strict_filter',
                'description' => 'Strict filter is recommended for production because some providers reject unsupported currency combinations.',
            ],
        ], $currencyFields);
    }

    public function boot()
    {
        ExtensionHelper::registerMiddleware(Middleware\ResolveCurrencyForGateway::class);

        $allowedGatewaysByCurrency = $this->allowedGatewaysByCurrency();

        Gateway::addGlobalScope('currency_gateway_controller', function (Builder $builder) use ($allowedGatewaysByCurrency) {
            if (!$this->shouldApplyScope()) {
                return;
            }

            $currency = $this->resolveCurrentCurrency();
            if (!$currency) {
                return;
            }

            $currency = strtoupper($currency);
            if (!array_key_exists($currency, $allowedGatewaysByCurrency)) {
                return;
            }

            $allowedIds = $allowedGatewaysByCurrency[$currency];
            if ($allowedIds === []) {
                return;
            }

            if ($this->visibilityMode() === 'prefer_configured') {
                $placeholders = implode(',', array_fill(0, count($allowedIds), '?'));
                $builder->orderByRaw("CASE WHEN id IN ($placeholders) THEN 0 ELSE 1 END", $allowedIds);

                return;
            }

            $builder->whereIn('id', $allowedIds);
        });
    }

    private function allowedGatewaysByCurrency(): array
    {
        $rules = [];

        foreach ($this->config as $key => $value) {
            if (!str_starts_with($key, 'currency_') || !str_ends_with($key, '_gateways')) {
                continue;
            }

            $currency = strtoupper(substr($key, strlen('currency_'), -strlen('_gateways')));

            if (!is_array($value) || count($value) === 0) {
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

    private function gatewaySettingKey(string $currencyCode): string
    {
        return 'currency_' . strtolower($currencyCode) . '_gateways';
    }

    private function gatewayCurrencySettingKey(string $currencyCode): string
    {
        return 'currency_' . strtolower($currencyCode) . '_gateway_currency';
    }

    private function resolveCurrentCurrency(): ?string
    {
        $selectedCurrency = session('currency', config('settings.default_currency'));
        if (!$selectedCurrency) {
            return null;
        }

        $selectedCurrency = strtoupper($selectedCurrency);
        $gatewayCurrency = $this->config[$this->gatewayCurrencySettingKey($selectedCurrency)] ?? null;

        if (!is_string($gatewayCurrency) || $gatewayCurrency === '') {
            return $selectedCurrency;
        }

        return strtoupper($gatewayCurrency);
    }

    private function visibilityMode(): string
    {
        $mode = $this->config['gateway_visibility_mode'] ?? 'strict_filter';

        return in_array($mode, ['prefer_configured', 'strict_filter'], true)
            ? $mode
            : 'strict_filter';
    }

    private function shouldApplyScope(): bool
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
}
