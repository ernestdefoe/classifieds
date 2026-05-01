<p align="center">
  <h1 align="center">Classifieds</h1>
</p>

<p align="center">
  <img alt="License" src="https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square">
  <a href="https://packagist.org/packages/ramon/classifieds">
    <img alt="Latest Stable Version" src="https://img.shields.io/packagist/v/ramon/classifieds.svg?style=flat-square">
  </a>
  <a href="https://packagist.org/packages/ramon/classifieds">
    <img alt="Total Downloads" src="https://img.shields.io/packagist/dt/ramon/classifieds.svg?style=flat-square">
  </a>
  <a href="https://github.com/ram0ng1/classifieds/releases/latest">
    <img alt="GitHub Release" src="https://img.shields.io/github/v/release/ram0ng1/classifieds?style=flat-square&label=release&color=success">
  </a>
  <a href="https://donate.stripe.com/fZe5o66nebkf39S28a">
    <img alt="Donate" src="https://img.shields.io/badge/donate-stripe-%236772E5?style=flat-square">
  </a>
</p>

<p align="center">
  Turn any tag in your <a href="https://flarum.org">Flarum</a> forum into a classified-ads section: photo galleries, price &amp; price ranges, location, sold/bumped status, OLX-inspired listing cards and a dedicated discussion page that replaces the standard hero with a marketplace-style layout. Originally written for Flarum 0.1 by <a href="https://jasonclemons.me">Jason Clemons</a>, completely rebuilt for Flarum 2.0.
</p>

---

## Features

### Marketplace UX
- **OLX-style listing cards** — Discussions in classifieds tags show as horizontal cards with cover photo, price, label badge, location, and time
- **Dedicated discussion page** — Two-column hero (gallery + info), breadcrumb, contact-seller button, share/copy-link, edit, and a seller card showing per-tag listing count
- **Photo carousel** — Up to 20 photos per listing, drag-and-drop upload at compose time, lightbox-ready hero carousel with thumbnails
- **Subtle tag-color tint** — The hero background gets a soft fade in the tag's color, plus a thin accent stripe — identity without being loud
- **Currency-aware prices** — Price + optional max (range), automatic symbol resolution (`USD → $`, `BRL → R$`, `EUR → €`, …) with a forum-wide default fallback

### Listing fields
- **Labels** — `ISO`, `WTB`, `WTS`, `TRADE` out of the box; admin can add custom labels (`OLX`, `PROMO`, `TROCA`, etc.) via comma-separated config
- **Status** — `active`, `sold`, `completed` with event-posts in the stream when changed
- **Bump** — Re-promote your listing with a single click, also recorded as an event-post
- **Edit-after-create** — Modal to update label/price/range/currency/location at any time
- **Required fields** — Admin toggles for required label / price / location

### Admin
- **Per-tag opt-in** — Pick which tags act as classifieds tags via the native flarum-tags select-tags widget
- **Default currency code** — One source of truth for currency symbol; per-listing override available
- **Auto-prune** — Scheduled command removes sold/inactive listings after N days; configurable
- **Permissions** — `discussion.markListingSold`, `discussion.bumpListing`, `discussion.editListing`

### Composer
- **Tag-gated extension** — The classifieds composer (image uploader + label pills + price row + location) only activates when at least one of the selected tags is flagged as classifieds in the admin
- **Native styling** — Pills built on Flarum's `.Button` component, inputs use `FormControl`, all Flarum theme tokens (`--primary-color`, `--control-bg`, `--muted-color`, …) — drops in cleanly with any theme

## Requirements

- Flarum `^2.0.0`
- [flarum/tags](https://github.com/flarum/tags) (used for the per-tag opt-in widget)

## Installation

```sh
composer require ramon/classifieds
php flarum migrate
php flarum cache:clear
```

Then enable **Classifieds** in **Admin → Extensions** and pick the tags that should behave as classifieds.

## Updating

```sh
composer update ramon/classifieds --with-dependencies
php flarum migrate
php flarum cache:clear
```

## Configuration

All settings are available in the admin panel under the Classifieds extension:

| Setting | Description | Default |
|---|---|---|
| Classifieds tags | Tags that turn into classifieds (uses flarum-tags' selector) | — |
| Default currency code | 3-letter ISO code used when a listing has no explicit currency | `USD` |
| Allowed listing labels | Comma-separated list of labels available in the composer | `iso,wtb,wts,trade` |
| Allow price ranges | Show a max-price field beside the price | `true` |
| Show currency symbol | Render `$/€/R$` instead of the raw ISO code | `true` |
| Require a listing label | Make label selection mandatory | `true` |
| Require a price | Make price field mandatory | `true` |
| Require a location | Make location field mandatory | `false` |
| Auto-prune after N days | Delete listings older than N days; `0` disables | `0` |
| Auto-prune sold listings | When pruning runs, also remove sold/completed listings | `false` |

## Permissions

| Permission | Default group | Description |
|---|---|---|
| `discussion.markListingSold` | Member | Mark own listings as sold/active/completed |
| `discussion.bumpListing` | Member | Bump (re-promote) own listing |
| `discussion.editListing` | Member | Edit own listing fields after creation |

## API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/classifieds/listings/{id}/screenshots` | Upload a photo to the listing (multipart `file`) |
| `DELETE` | `/api/classifieds/listings/{id}/screenshots` | Remove a photo (`{ filename }`) |

The `Discussion` resource is extended with `listingLabel`, `listingStatus`, `listingPrice`, `listingPriceMax`, `listingCurrency`, `listingLocation`, `listingSoldAt`, `listingBumpedAt`, `listingImages`, plus the booleans `isClassifieds`, `canMarkListingSold`, `canBumpListing`, `canEditListing`.

The `User` resource is extended with `classifiedsListingsCount` (number of listings in classifieds tags only — *not* the user's total discussion count).

The `Tag` resource is extended with `isClassifieds`.

## Console

```sh
# Prune sold/expired listings according to admin settings
php flarum classifieds:prune

# Override the retention window for this run only
php flarum classifieds:prune --days=30

# See what would be deleted without committing
php flarum classifieds:prune --days=30 --dry-run

# Also remove listings that are still active beyond the window
php flarum classifieds:prune --days=30 --include-active
```

The command is also scheduled to run **daily** automatically via `Extend\Console::schedule`.

## Links

- [GitHub](https://github.com/ram0ng1/classifieds)
- [Issues](https://github.com/ram0ng1/classifieds/issues)
- [Donate](https://donate.stripe.com/fZe5o66nebkf39S28a)

## Authors

- [Ramon Guilherme](https://ramonguilherme.com.br) — Flarum 2.0 rewrite, photo galleries, OLX-style hero, per-tag opt-in, marketplace UX
- [Jason Clemons](https://jasonclemons.me) — original Flarum 0.1 classifieds concept

## License

[MIT](LICENSE)
