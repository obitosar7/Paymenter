<?php

namespace Paymenter\Extensions\Others\VatManager\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Paymenter\Extensions\Others\VatManager\Models\VatProductSetting;

class VatManagerController extends Controller
{
    public function index()
    {
        $products = Product::orderBy('name')->get();
        $vatSettings = VatProductSetting::pluck('enabled', 'product_id');

        return view('vat-manager::index', compact('products', 'vatSettings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $enabledProducts = collect($request->input('product_vat_enabled', []))->map(fn ($value) => (int) $value);

        Product::query()->pluck('id')->each(function ($productId) use ($enabledProducts) {
            VatProductSetting::updateOrCreate(
                ['product_id' => $productId],
                ['enabled' => $enabledProducts->contains((int) $productId)]
            );
        });

        return back()->with('success', 'VAT product settings updated successfully.');
    }
}
