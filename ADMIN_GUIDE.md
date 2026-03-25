# Ad Management Extension — Admin Guide

This guide covers everything you need to set up and manage the Ad Management extension.

---

## Table of Contents

1. [Overview](#overview)
2. [Initial Setup Checklist](#initial-setup-checklist)
3. [Ad Zones](#ad-zones)
4. [Managing Advertisements](#managing-advertisements)
5. [Approving & Rejecting Ads](#approving--rejecting-ads)
6. [Settings Reference](#settings-reference)
7. [Email Notifications](#email-notifications)
8. [Analytics](#analytics)
9. [User Ad Submissions](#user-ad-submissions)
10. [Post Shortcodes](#post-shortcodes)
11. [Image Processing](#image-processing)
12. [Permissions](#permissions)
13. [Console Commands](#console-commands)
14. [Security & Anti-Fraud](#security--anti-fraud)

---

## Overview

The Ad Management extension lets you display image, HTML, and Google AdSense ads across your forum. Ads are organized into **zones** (named locations on the page). You create zones, assign ads to them, and optionally allow forum members to submit their own ads for review.

Key capabilities:
- Multiple ad types: image banners, raw HTML/JS, Google AdSense
- Per-zone dimensions that automatically resize uploaded images
- Built-in click and impression tracking with analytics charts
- Approval workflow for user-submitted ads and image changes
- Automated email reminders for expiring ads and performance reports
- Group-based visibility (show ads only to certain member groups)
- BBCode shortcodes to place ads inline in posts

---

## Initial Setup Checklist

1. **Create at least one Ad Zone** — Go to *Ad Management → Zones* and create a zone with a position (e.g., `below_header`).
2. **Create an Ad** — Go to *Advertisements*, click *Create Ad*, pick the zone, set the type and content.
3. **Configure settings** — Go to *Settings* and adjust impression tracking, image formats, and notifications.
4. **Set up the cron job** (for email notifications) — Add this to your server's crontab:
   ```
   * * * * * php /path/to/flarum ad-management:send-notifications >> /dev/null 2>&1
   ```
   Once per day is sufficient for most forums.
5. **(Optional) Grant submission permission** — Go to *Admin → Permissions* and assign the *Submit Advertisements* permission to the member groups you want to allow.

---

## Ad Zones

Zones define where ads appear on the page. Each zone has a position, optional dimension limits, and can hold multiple ads.

### Positions

| Position | Where it appears |
|---|---|
| `header` | Fixed bar at the very top, above the nav |
| `below_header` | Below the main header/hero, above content |
| `between_posts` | Between discussion replies (controlled by the interval setting) |
| `sidebar` | Right sidebar on the index page |
| `above_footer` | Above the forum footer |
| `footer` | Inside the footer bar |
| `custom` | Does not auto-render — only appears via shortcode |

### Zone Fields

| Field | Description |
|---|---|
| **Zone Name** | Unique lowercase identifier (e.g., `top_banner`). Used in BBCode shortcodes. |
| **Display Label** | Human-readable name shown in the admin table. |
| **Description** | Optional notes for your own reference. |
| **Position** | Where the zone auto-renders on the page. |
| **Active** | Toggle the entire zone on/off. |
| **Sort Order** | Lower numbers render first when multiple zones share a position. |
| **Max Width / Max Height** | If set, uploaded ad images are automatically resized to fit within these dimensions (in pixels). |

### Tips

- You can have multiple zones in the same position (e.g., two `sidebar` zones). They'll stack vertically in sort order.
- A zone marked **Default** cannot be deleted — it ships with the extension and is used for fallback placement.
- When you delete a zone, all ads assigned to it are also deleted. Make sure to reassign important ads first.
- Custom zones with no `max_width`/`max_height` skip resizing entirely.

---

## Managing Advertisements

Go to *Ad Management → Advertisements* to see all ads. Use the filter bar to narrow by status: **All / Active / Pending Review / Inactive / Rejected**.

### Ad Types

| Type | Description |
|---|---|
| **Image** | Displays an image with an optional link. This is the only type user-submitted ads can be. |
| **HTML** | Renders arbitrary HTML/JS code. Use for custom banners or third-party ad networks. |
| **Google AdSense** | Paste your AdSense unit code. The extension loads the AdSense script automatically. |

### Creating/Editing an Ad

| Field | Notes |
|---|---|
| **Name** | Internal label — not shown to forum visitors. |
| **Type** | Image, HTML, or AdSense. |
| **Zone** | Which zone this ad belongs to. |
| **Content** | HTML/JS code for HTML or AdSense types. |
| **Image URL** | Remote URL to the ad image. |
| **Link URL** | Where clicking the ad goes. Opens in a new tab. |
| **Alt Text** | Screen reader text for the image. |
| **Width / Height** | Dimensions used when rendering the ad in the HTML. Does not replace zone-level resizing. |
| **Active** | Enable or disable without deleting. |
| **Start Date / End Date** | Schedule when the ad runs. Leave blank for always-on. |
| **Priority** | Higher number = shown first when multiple ads compete for a zone. Default is 0. |
| **Group Visibility** | Comma-separated group IDs. Leave blank to show to everyone. Enter `1` to show only to admins, for example. |
| **Max Impressions** | Auto-deactivate after this many views. Leave blank for unlimited. |
| **Max Clicks** | Auto-deactivate after this many clicks. Leave blank for unlimited. |
| **Max Image Changes** | How many times the ad owner can change the image. Defaults to the global setting. Set to 0 for unlimited. |
| **Ad Owner** | The user this ad belongs to (shown in "My Ads"). Leave blank for admin-owned ads with no owner. |

### Status Meanings

| Status | Meaning |
|---|---|
| **Active** | Running and visible to eligible users. |
| **Inactive** | Disabled by admin or by hitting an impression/click cap. |
| **Pending Review** | Submitted by a user, awaiting your approval. |
| **Rejected** | You reviewed and declined it. |

### Deleting an Ad

Click the trash icon in the actions column. This also deletes any locally-stored processed image for that ad. This action cannot be undone.

---

## Approving & Rejecting Ads

### New Ad Submissions

When a forum member submits an ad, it appears with a **Pending (N)** badge on the *Advertisements* tab header and its row is highlighted yellow.

1. Filter to **Pending Review** to see only new submissions.
2. Review the ad name, image URL, and zone.
3. Click the **✓ (green check)** to approve — the ad becomes Active and goes live.
4. Click the **✗ (red X)** to reject — the ad is marked Rejected and hidden from the forum.

You can re-approve a rejected ad at any time by clicking Edit and changing the status to Active.

### Image Change Requests

If **Require Approval for Image Changes** is enabled in Settings, ad owners can request an image change that won't go live until you approve it.

When a pending image change exists on an ad, an **"Image Pending"** badge appears next to the ad name, and two additional buttons appear in the actions column:

- **🖼 (image icon)** — Approve the new image. The old image is replaced.
- **🚫 (ban icon)** — Reject the change. The existing image is kept.

If the setting is disabled, image changes are applied immediately without review.

---

## Settings Reference

Navigate to *Ad Management → Settings* to configure these options.

### General

| Setting | Default | Description |
|---|---|---|
| **Posts Between Ads** | 5 | Show a `between_posts` zone ad after every N posts in a discussion. Set to 0 to disable. |
| **Default Max Image Changes** | 5 | New ads get this limit by default. Override per ad if needed. |
| **Track Impressions** | On | Count how many times each ad is displayed. Disable to reduce database writes on high-traffic forums. |
| **Track Clicks** | On | Record every click on an ad link. |
| **Hide Ads for Groups** | (blank) | Comma-separated group IDs (e.g., `4,5`) that should never see any ads. Useful for premium subscriber groups. |
| **Google AdSense Publisher ID** | (blank) | Your `ca-pub-XXXXXXXXXXXXXXXX` ID. Required for AdSense ads to load. |

### Image Settings

| Setting | Default | Description |
|---|---|---|
| **Allowed Image Formats** | jpg,jpeg,png,webp,gif | Comma-separated list. URLs with other extensions are rejected at submission. |
| **Enable Image Compression** | Off | Automatically compress image ads when they are first submitted or updated. |
| **Compression Quality** | 85 | Quality value 1–100. Applies to JPEG and WebP. For PNG, higher quality = less deflate compression. Recommended: 80–90. |
| **Compression Method** | PHP GD | **PHP GD** uses the server's built-in GD library (lossy). **reSmush.it API** sends the image to the reSmush.it external service for lossless optimization (requires PHP `curl`). |
| **Require Approval for Image Changes** | Off | When on, image changes from ad owners are held in a staging field until you approve them. |

### Email Notifications

| Setting | Default | Description |
|---|---|---|
| **Expiration Reminder (Days)** | 7 | Send a reminder email to ad owners this many days before their ad expires. Set to 0 to disable. |
| **Send Performance Reports** | Off | Include an aggregate performance summary email for each ad owner when the cron job runs. |

#### Customizing Email Templates

You can override the default email subjects and bodies using the template fields. Leave a field blank to use the built-in default.

**Expiration email placeholders:**

| Placeholder | Value |
|---|---|
| `{forum_title}` | Your forum name |
| `{forum_url}` | Your forum URL |
| `{owner_name}` | Ad owner's display name |
| `{owner_username}` | Ad owner's username |
| `{ad_name}` | Name of the expiring ad |
| `{days_left}` | "1 day" or "X days" |
| `{expiry_date}` | Formatted expiry date (e.g., March 28, 2026) |
| `{impressions}` | Total impression count |
| `{clicks}` | Total click count |

**Performance report placeholders:**

| Placeholder | Value |
|---|---|
| `{forum_title}` | Your forum name |
| `{forum_url}` | Your forum URL |
| `{owner_name}` | Ad owner's display name |
| `{owner_username}` | Ad owner's username |
| `{ad_count}` | Number of active ads they own |
| `{total_impressions}` | Sum of impressions across all their ads |
| `{total_clicks}` | Sum of clicks across all their ads |
| `{ctr}` | Overall click-through rate percentage |
| `{ad_lines}` | Formatted list of individual ad stats |

---

## Email Notifications

Notifications are sent by the console command:

```bash
php flarum ad-management:send-notifications
```

Add this to your server's crontab to run automatically. Running once per day is typically enough:

```
0 8 * * * php /var/www/html/flarum ad-management:send-notifications >> /dev/null 2>&1
```

*(Adjust the path to your Flarum installation and the time as needed.)*

**What the command does each run:**

1. **Expiration reminders** — Finds all active ads with an `end_date` within your configured reminder window and sends one email per ad to its owner.
2. **Performance reports** — If enabled, groups all active ads by owner and sends one summary email per owner.

The command only emails owners who have a valid email address on file. If no email is configured for an owner, that notification is silently skipped.

---

## Analytics

Go to *Ad Management → Analytics* to view click and impression data for any ad.

1. Select an ad from the dropdown.
2. Choose a period: **Last 7 Days**, **Last 30 Days**, or **Last 90 Days**.
3. The dashboard shows:
   - **Total Impressions** — All-time impression count.
   - **Total Clicks** — All-time click count.
   - **CTR** — Click-through rate (clicks ÷ impressions × 100%).
   - **Clicks by Day** — Bar chart of daily click volume over the selected period.

> **Note:** Total impressions and clicks are all-time figures. The chart only shows clicks within the selected period window.

Ad owners can also view their own analytics from the *My Ads* page at `/u/{username}/ads`.

---

## User Ad Submissions

Forum members with the **Submit Advertisements** permission can submit image ads for review from their profile's "My Ads" page.

### Granting the Permission

Go to *Admin → Permissions* → find **Submit Advertisements** under the Reply section. Grant it to any group whose members should be able to submit ads (e.g., the Members group).

### User Submission Workflow

1. User navigates to their profile → **My Ads**.
2. User clicks **Submit Ad** and fills in the form:
   - Ad Name, Zone, Image URL, Link URL, Alt Text
3. User submits — the ad is saved with status `Pending Review` and is not visible on the forum.
4. You receive a pending count badge in the admin *Advertisements* tab.
5. You approve or reject it (see [Approving & Rejecting Ads](#approving--rejecting-ads)).

User-submitted ads are always **Image type** — HTML and AdSense types are admin-only.

### Image Change Limits

Each ad has a **Max Image Changes** setting. Once a user has changed their ad image that many times, the Change Image button is replaced with "No image changes remaining."

The count is tracked per ad and applies only to non-admins. Admins can always change images.

---

## Post Shortcodes

You can embed ads from any zone directly inside a forum post by using a shortcode:

```
{myadvertisements[zone_name]}
```

Replace `zone_name` with the exact zone name you created (e.g., `top_banner`).

**Example:**

If you have a zone named `promo`, paste this anywhere in a post:

```
{myadvertisements[promo]}
```

The extension converts this to a rendered ad block when the post is displayed.

**Tips:**
- The zone must be **Active** and have at least one **Active** ad assigned to it.
- Use a `custom` position zone for shortcode-only placements so it doesn't also auto-render in a fixed page location.
- This works in discussion posts and any other content that goes through Flarum's text formatter.
- The zone name in the shortcode is case-insensitive.

---

## Image Processing

When an image URL is submitted, the extension can automatically resize and/or compress it before storing a local copy.

### Resizing

Resizing runs automatically when a zone has `max_width` and/or `max_height` configured. The image is scaled down to fit within that bounding box while preserving its aspect ratio. Images that are already within the bounds are not touched.

- Requires the PHP **GD** extension to be installed.
- Supported formats: JPEG, PNG, WebP (GIF is not resized).
- PNG transparency is preserved.

### Compression

Compression only runs when **Enable Image Compression** is turned on in Settings. Two methods are available:

**PHP GD (default):**
- Lossless for PNG (deflate), lossy for JPEG and WebP.
- No external dependencies — works on any server with GD installed.
- Uses the **Compression Quality** setting.

**reSmush.it API:**
- Uploads the image to the reSmush.it free optimization service.
- Lossless metadata stripping for JPEG/PNG/WebP.
- Requires PHP `curl` to be enabled.
- Falls back silently to GD if the API is unreachable.
- The service is free and has no sign-up requirement.

### Local Storage

When an image is resized or compressed, the result is stored locally at:

```
/public/assets/ad-images/{hash}.{ext}
```

The original remote URL is used as-is if no processing was needed.

When an ad or its image is deleted/replaced, the locally stored file is also deleted.

> **Size limit:** Images larger than 5 MB cannot be downloaded for processing. The original URL is used instead.

---

## Permissions

| Permission | Who needs it | What it allows |
|---|---|---|
| **Admin** | Forum administrators | Full access to all extension features |
| **Submit Advertisements** (`ralkage-ad-management.submitAd`) | Grant to member groups | Submit image ads for review via the "My Ads" page |

### What Users Can Do

Logged-in users with the Submit Advertisements permission can:
- Submit new image ads (pending review)
- View their own ads and stats on `/u/{username}/ads`
- Request image changes (subject to the per-ad limit)
- View their own analytics

Users **cannot**:
- Create HTML or AdSense type ads
- Set their own start/end dates, priority, or group visibility
- Approve or reject ads
- View other users' ads
- Delete their own ads (contact admin)

### Guests

Guests can see ads on the forum (unless their group is excluded via *Hide Ads for Groups*). They cannot click-track in a meaningful way and cannot access any management features.

---

## Console Commands

| Command | Description |
|---|---|
| `php flarum ad-management:send-notifications` | Send expiration reminders and performance reports to ad owners |
| `php flarum ad-management:purge-clicks [days]` | Delete `ad_clicks` records older than N days (default: 90) |

### GDPR Click Data Purge

The purge command deletes click tracking records older than the specified number of days, helping you comply with data retention policies:

```bash
# Delete clicks older than 90 days (default)
php flarum ad-management:purge-clicks

# Delete clicks older than 30 days
php flarum ad-management:purge-clicks 30
```

Add this to cron to run monthly:

```
0 2 1 * * php /var/www/flarum ad-management:purge-clicks 90 >> /dev/null 2>&1
```

> **Note:** This permanently deletes tracking records. Analytics for past periods will show zero for deleted data. Run with a higher day count (e.g., 365) to keep more history.

---

## Security & Anti-Fraud

### Rate Limiting

Tracking endpoints are protected against click fraud and impression spam:

| Endpoint | Limit |
|---|---|
| Click tracking (`/ad-track/click`) | 5 clicks per ad per IP address per hour |
| Impression tracking (`/ad-track/impression`) | 60 batch requests per IP address per hour |

Requests that exceed the limit receive an HTTP `429 Too Many Requests` response and are silently discarded. Legitimate users will never notice this limit during normal browsing.

**Per-user deduplication:** Logged-in users are additionally rate-limited to one recorded click per ad per hour, regardless of IP address.

### SSRF Protection

All externally-fetched image URLs are validated before download. The extension blocks requests to:

- Loopback addresses (`127.x.x.x`)
- Private networks (`10.x.x.x`, `172.16–31.x.x`, `192.168.x.x`)
- Link-local addresses (`169.254.x.x`)
- CGNAT ranges (`100.64–127.x.x`)

Only `http://` and `https://` schemes are accepted for image URLs and link URLs.

### Input Validation

- Zone names must be lowercase alphanumeric + underscores (`^[a-z0-9_]+$`), max 50 characters, and globally unique
- Link URLs must use `http://` or `https://` scheme
- Zone labels are required (max 100 characters)

---

## Quick Reference

| Task | Where |
|---|---|
| Create/edit ad zones | Ad Management → Zones |
| Create/edit/delete ads | Ad Management → Advertisements |
| Approve/reject pending ads | Ad Management → Advertisements (filter: Pending Review) |
| Change global settings | Ad Management → Settings |
| View click/impression data | Ad Management → Analytics |
| Grant user submission rights | Admin → Permissions → Submit Advertisements |
| Send notification emails | `php flarum ad-management:send-notifications` |
| Place ads in posts | `{myadvertisements[zone_name]}` in post content |
