# Dual Currency Router

This extension keeps **display currency** and **payment processing currency** separate.

## What it does

- Captures the customer-facing currency from cart/invoice context.
- Resolves an optional mapped processing currency in the background.
- Filters or prioritizes gateways using the resolved processing currency.
- Prevents unsupported currency/gateway combinations by centralizing rules in settings.

## Typical setup

1. Keep storefront/invoice prices in `USD` (display).
2. Map `USD -> EUR` as processing currency if your gateway account settles in EUR.
3. Define allowed gateways for `EUR`.
4. Choose strict mode for hard filtering, or prefer mode for soft prioritization.

## Notes

- If no mapping is defined, processing currency defaults to display currency.
- If no gateway list is defined for a processing currency, all gateways remain available.
