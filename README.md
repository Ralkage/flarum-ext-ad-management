# Ad Management — Flarum Extension

A comprehensive ad management system for [Flarum](https://flarum.org) forums. Supports image banners, HTML/JS ads, and Google AdSense with zones, analytics, an approval workflow, and a user self-service portal.

## Features

- **Ad Zones** — Named positions (`header`, `below_header`, `between_posts`, `sidebar`, `above_footer`, `footer`, `custom`) with optional dimension constraints
- **Ad Types** — Image banners, raw HTML/JS, and Google AdSense units
- **Rotation** — One random ad per zone per page load (randomized each visit)
- **Analytics** — Per-ad impression and click tracking with daily breakdowns and CTR
- **Approval Workflow** — User-submitted ads queue for admin review before going live; optional approval for image changes
- **Email Notifications** — Expiration reminders and performance reports via cron, with customizable templates
- **Admin Notifications** — Instant email alert when a user submits an ad for review
- **Rate Limiting** — Anti-fraud protection on click and impression tracking endpoints
- **Group Visibility** — Show ads only to specific member groups; hide ads for premium groups
- **Post Shortcodes** — Embed any zone inline in discussion posts: `{myadvertisements[zone_name]}`
- **Image Processing** — Automatic resizing to zone dimensions; optional compression via PHP GD or reSmush.it API
- **GDPR Compliance** — Purge old click records with the built-in console command

## Requirements

- Flarum `^1.8`
- PHP `^8.0` with GD extension (for image resizing/compression)
- PHP `curl` extension (optional, for reSmush.it API compression)

## Installation

```bash
composer require ralkage/flarum-ext-ad-management
```

Then enable it in your Flarum admin panel under **Extensions**.

## Quick Start

1. Go to **Admin → Ad Management → Zones** and create a zone (or use a built-in default zone).
2. Go to **Advertisements** and create your first ad.
3. Set up the cron job for email notifications:
   ```
   0 8 * * * php /var/www/flarum ad-management:send-notifications >> /dev/null 2>&1
   ```
4. (Optional) Grant the **Submit Advertisements** permission to allow forum members to submit ads for review.

## Console Commands

| Command | Description |
|---|---|
| `php flarum ad-management:send-notifications` | Send expiration reminders and performance reports |
| `php flarum ad-management:purge-clicks [days]` | Delete click records older than N days (default: 90) for GDPR compliance |

## Documentation

See [ADMIN_GUIDE.md](ADMIN_GUIDE.md) for full configuration and usage documentation.

## License

MIT — see [LICENSE](LICENSE).
