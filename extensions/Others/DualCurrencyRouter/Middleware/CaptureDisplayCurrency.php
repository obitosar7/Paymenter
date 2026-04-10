<?php

namespace Paymenter\Extensions\Others\DualCurrencyRouter\Middleware;

use App\Models\Invoice;
use Closure;
use Illuminate\Http\Request;

class CaptureDisplayCurrency
{
    public function handle(Request $request, Closure $next)
    {
        $displayCurrency = $this->resolveDisplayCurrency($request);

        if ($displayCurrency) {
            $request->attributes->set('dual_currency.display_currency', $displayCurrency);
            $request->session()->put('dual_currency.display_currency', $displayCurrency);
        }

        return $next($request);
    }

    private function resolveDisplayCurrency(Request $request): ?string
    {
        $invoiceCurrency = $this->currencyFromRouteInvoice($request);
        if ($invoiceCurrency) {
            return $invoiceCurrency;
        }

        $directCurrency = $request->input('currency') ?? $request->input('currency_code');
        if (is_string($directCurrency) && $directCurrency !== '') {
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

        if (is_array($snapshot) && isset($snapshot['data']['currency'])) {
            return strtoupper((string) $snapshot['data']['currency']);
        }

        $updates = $component['updates'] ?? [];
        if (is_array($updates)) {
            if (isset($updates['currency'])) {
                return strtoupper((string) $updates['currency']);
            }

            foreach ($updates as $update) {
                if (is_array($update) && ($update['path'] ?? null) === 'currency' && isset($update['value'])) {
                    return strtoupper((string) $update['value']);
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
            return strtoupper((string) $params['currency']);
        }

        if (isset($params['currency_code'])) {
            return strtoupper((string) $params['currency_code']);
        }

        return null;
    }
}
