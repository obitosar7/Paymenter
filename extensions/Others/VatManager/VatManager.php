<?php

namespace Paymenter\Extensions\Others\VatManager;

use App\Classes\Extension\Extension;
use App\Events\Invoice\Finalized;
use App\Events\Invoice\Updated;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Paymenter\Extensions\Others\VatManager\Listeners\ApplyVatToInvoice;

class VatManager extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'enabled',
                'label' => 'Enable VAT',
                'type' => 'checkbox',
                'database_type' => 'boolean',
                'default' => false,
            ],
            [
                'name' => 'vat_rate',
                'label' => 'VAT Rate',
                'type' => 'number',
                'suffix' => '%',
                'default' => 14,
                'required' => true,
                'validation' => 'numeric|min:0|max:100',
            ],
            [
                'name' => 'price_display',
                'label' => 'Price Display Mode',
                'type' => 'select',
                'default' => 'exclusive',
                'options' => [
                    'exclusive' => 'VAT Exclusive (show VAT as extra line item)',
                    'inclusive' => 'VAT Inclusive (line item still shown)',
                ],
            ],
        ];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations(__DIR__ . '/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations(__DIR__ . '/database/migrations');
    }

    public function boot()
    {
        require __DIR__ . '/routes/web.php';
        View::addNamespace('vat-manager', __DIR__ . '/resources/views');

        Event::listen(Finalized::class, ApplyVatToInvoice::class);
        Event::listen(Updated::class, ApplyVatToInvoice::class);

        Event::listen('navigation', function () {
            return [
                'name' => 'VAT Manager',
                'route' => 'extensions.vat-manager.index',
                'icon' => 'ri-percent',
                'separator' => false,
                'children' => [],
            ];
        });
    }
}
