# Simply Connect SMS for PrestaShop

Official PrestaShop module for transactional SMS through [Simply Connect](https://simply-connect.ovh).

## Features

- automatic messages after selected order status changes;
- per-status templates with safe order placeholders;
- manual messages and history on the admin order screen;
- durable database outbox and authenticated cron worker;
- stable idempotency keys, bounded retries and explicit `UNKNOWN` outcomes;
- delivery status reconciliation;
- encrypted API-key storage and server constant overrides.

## Installation

Upload `simplyconnectsms.zip` in **Modules > Module Manager**, install it, then configure an API key with `SMS_SEND` and `MESSAGES_READ`, refresh endpoints and select a sender, statuses and templates. Add the displayed authenticated cron URL to a scheduler running every minute.

For managed installations, define `SIMPLY_CONNECT_API_KEY` outside the database. `SIMPLY_CONNECT_API_BASE_URL` exists for development against the included local mock API.

## Development

```bash
composer install
composer check
php scripts/build-release.php
```

The shared local environment in `modern-simply-connect-platform` starts PrestaShop, WordPress/WooCommerce and a no-cost mock External API.

## Security

Report vulnerabilities privately through GitHub Security Advisories. Never post API keys or customer phone numbers in public issues.

## License

GPL-2.0-or-later.
