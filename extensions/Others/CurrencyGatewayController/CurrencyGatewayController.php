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

        return Currency::query()
            ->orderBy('code')
            ->get()
            ->map(function (Currency $currency) use ($gatewayOptions) {
                return [
                    'name' => $this->gatewaySettingKey($currency->code),
                    'label' => "{$currency->code} Allowed Gateways",
                    'type' => 'select',
                    'multiple' => true,
                    'options' => $gatewayOptions,
                    'database_type' => 'array',
                    'description' => "Select the gateways available when paying in {$currency->code}. Leave empty to allow all gateways.",
                ];
            })
            ->values()
            ->toArray();
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

    private function resolveCurrentCurrency(): ?string
    {
        return session('currency', config('settings.default_currency'));
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
