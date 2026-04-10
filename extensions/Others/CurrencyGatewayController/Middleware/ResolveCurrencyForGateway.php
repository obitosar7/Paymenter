<?php

namespace Paymenter\Extensions\Others\CurrencyGatewayController\Middleware;

use App\Models\Invoice;
use Closure;
use Illuminate\Http\Request;

class ResolveCurrencyForGateway
{
    public function handle(Request $request, Closure $next)
    {
        $currency = $this->resolveCurrency($request);

        if ($currency) {
            $request->session()->put('currency', $currency);
        }

        return $next($request);
    }

    private function resolveCurrency(Request $request): ?string
    {
        $invoiceCurrency = $this->currencyFromRouteInvoice($request);
        if ($invoiceCurrency) {
            return $invoiceCurrency;
        }

        $directCurrency = $request->input('currency') ?? $request->input('currency_code');
        if ($directCurrency) {
            return strtoupper($directCurrency);
        }

        $livewireCurrency = $this->currencyFromLivewirePayload($request);
        if ($livewireCurrency) {
            return $livewireCurrency;
        }

        return $this->currencyFromReferer($request);
    }

    private function currencyFromRouteInvoice(Request $request): ?string
    {
        $invoice = $request->route('invoice');
        if (!$invoice) {
            return null;
        }

        if ($invoice instanceof Invoice) {
            return strtoupper($invoice->currency_code);
        }

        $model = Invoice::whereKey($invoice)->first();

        return $model ? strtoupper($model->currency_code) : null;
    }

    private function currencyFromLivewirePayload(Request $request): ?string
    {
        $components = $request->input('components');
        if (!is_array($components) || empty($components)) {
            return null;
        }

        $component = $components[0] ?? [];
        $snapshot = $component['snapshot'] ?? null;
        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }

        if (is_array($snapshot)) {
            $data = $snapshot['data'] ?? [];
            if (isset($data['currency'])) {
                return strtoupper($data['currency']);
            }
        }

        $updates = $component['updates'] ?? [];
        if (is_array($updates)) {
            if (isset($updates['currency'])) {
                return strtoupper($updates['currency']);
            }

            foreach ($updates as $update) {
                if (is_array($update) && isset($update['path'], $update['value']) && $update['path'] === 'currency') {
                    return strtoupper($update['value']);
                }
            }
        }

        return null;
    }

    private function currencyFromReferer(Request $request): ?string
    {
        $referer = $request->header('referer');
        if (!$referer) {
            return null;
        }

        $path = parse_url($referer, PHP_URL_PATH) ?? '';
        if (preg_match('#/invoices/([^/]+)#', $path, $matches)) {
            $invoice = Invoice::whereKey($matches[1])->first();
            if ($invoice) {
                return strtoupper($invoice->currency_code);
            }
        }

        $query = parse_url($referer, PHP_URL_QUERY);
        if (!$query) {
            return null;
        }

        parse_str($query, $params);

        if (isset($params['currency'])) {
            return strtoupper($params['currency']);
        }

        if (isset($params['currency_code'])) {
            return strtoupper($params['currency_code']);
        }

        return null;
    }
}
