# VAT Manager Addon

This addon adds configurable VAT support to Paymenter without modifying core files.

## Features

- Adds VAT to invoices (including renewal invoices) by listening to invoice events.
- VAT is calculated as `invoice_item_subtotal * VAT_RATE / 100`.
- VAT is added as a separate invoice line item (`VAT (x.xx%)`).
- Configurable VAT rate from extension admin settings.
- Global VAT enable/disable from extension admin settings.
- Per-product VAT enable/disable from addon page.
- Supports VAT-inclusive or VAT-exclusive display mode labels.
- Works with existing gateways because it updates invoice items/total, which gateways already consume.

## Installation

1. Copy the folder to:
   `extensions/Others/VatManager`
2. In Paymenter admin, go to **Extensions** and enable **VAT Manager**.
3. Configure extension settings:
   - `Enable VAT`
   - `VAT Rate` (e.g., 14)
   - `Price Display Mode` (Inclusive/Exclusive)
4. Open **Admin → VAT Manager** and configure per-product VAT toggles.

## Usage

- When an invoice is finalized/updated and still pending, VAT Manager recalculates VAT and stores it as a dedicated invoice item.
- Renewal invoices are covered because they are regular invoices containing service-linked invoice items.
- If VAT is globally disabled, VAT line items are removed/not created.

## Notes

- This addon does not patch Paymenter core files.
- VAT line item uses listener reference metadata so it can be safely re-generated after invoice updates.
