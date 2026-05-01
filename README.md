# Flarum Classifieds (2.x)

A Classifieds extension for **Flarum 2.0** forums.

Originally written for Flarum 0.1.0 by [Jason Clemons](https://jasonclemons.me); fully ported to the Flarum 2.0 architecture (extenders, JSON:API resources, declarative admin page, console scheduling).

## Features

### User
- Listing labels (ISO / WTB / WTS / TRADE) — fully configurable
- Single price or price range with configurable currency code/symbol
- Location field
- Mark listing as **active**, **sold** or **completed** (event-post in the stream)
- Bump (re-promote) listings, with event-post
- Visual status (badge + dimmed list item)
- Edit listing fields after creation (`Edit listing` modal)

### Admin
- Per-tag opt-in: any tag can be flagged as a classifieds tag in the tag editor
- Settings page: default currency, allowed labels, currency symbol, price-range toggle, required-fields toggles, auto-prune
- Permissions: `discussion.markListingSold`, `discussion.bumpListing`, `discussion.editListing`
- Auto-prune scheduled command: `php flarum classifieds:prune`

## Installation

```bash
composer require ramon/classifieds:*
php flarum migrate
php flarum cache:clear
```

Then enable the extension in the admin dashboard.

## Console

```bash
php flarum classifieds:prune --days=30 --dry-run
```

The command is also scheduled daily via `Extend\Console::schedule`.

## License

MIT — see [LICENSE](LICENSE).
