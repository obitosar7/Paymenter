<x-filament-panels::page>
    <div class="space-y-4">
        <h2 class="text-xl font-bold">VAT Per Product Settings</h2>
        <p>Enable or disable VAT calculation for each product. Global VAT options are configured in the extension settings.</p>

        @if (session('success'))
            <div class="p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('extensions.vat-manager.update') }}" class="space-y-3">
            @csrf

            @foreach($products as $product)
                <label class="flex items-center gap-3 p-2 border rounded">
                    <input
                        type="checkbox"
                        name="product_vat_enabled[]"
                        value="{{ $product->id }}"
                        {{ ($vatSettings[$product->id] ?? true) ? 'checked' : '' }}
                    >
                    <span>{{ $product->name }}</span>
                </label>
            @endforeach

            <button type="submit" class="px-4 py-2 rounded bg-primary-600 text-white">Save VAT Product Settings</button>
        </form>
    </div>
</x-filament-panels::page>
