# Dual Currency Router

This extension keeps **display currency** and **payment processing currency** separate.

## What it does

- Captures the customer-facing currency from cart/invoice context.
- Resolves an optional mapped processing currency in the background.
- Filters or prioritizes gateways using the resolved processing currency.
- Prevents unsupported currency/gateway combinations by centralizing rules in settings.
- Keeps gateway internals untouched by converting currency/amount only at the payment boundary.

## Typical setup (USD display, EGP processing)

1. Set `Forced display currency` to `USD` so customer-facing pages stay in USD.
2. Map `USD -> EGP` as processing currency.
3. Set `USD -> Processing exchange rate` (for example `50` if `1 USD = 50 EGP`).
4. Define allowed gateways for `EGP`.
5. Choose **strict mode** for hard filtering of unsupported gateways.

## Notes

- If no mapping is defined, processing currency defaults to display currency.
- If mapped currency differs, payment amount is multiplied by the configured exchange rate before request creation.
- If no gateway list is defined for a processing currency, all gateways remain available.
- This approach avoids unsupported currency mismatch errors without changing core checkout or gateway logic.
