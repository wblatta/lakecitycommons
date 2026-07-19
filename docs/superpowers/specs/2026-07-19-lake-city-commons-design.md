# Lake City Commons — Design Spec

**Date:** 2026-07-19
**Status:** Approved by owner, pending spec review
**Codebase:** OlyHillsHub (this repo), evolved in place — OlyHillsHub is not yet live, so no migration concerns.

## Overview

Evolve OlyHillsHub into **Lake City Commons**, a public neighborhood news and community site for Lake City, Seattle (in the mold of myballard.com), while preserving the existing time-banking/item-sharing platform behind a feature flag for later launch as a member perk.

The site aggregates content from Lake City's many disparate organizations into one non-Facebook home: a weekly news digest, a merged events calendar, an organization directory, and a community submission form.

**Operating constraint:** the owner has 30–60 minutes per week for this site. All content collection and drafting is automated; the owner is the editor, never the writer.

**Business goal (not a software acceptance criterion):** $100–500/month within 6–12 months via local sponsors and reader memberships.

## Success criteria

1. A weekly digest is drafted automatically and publishable with ≤ 1 hour of editorial time.
2. The events calendar populates itself from at least 3 organization sources with no weekly effort.
3. Publishing a digest on the site and sending it as a newsletter is one action.
4. The existing community features (time banking, item sharing, messaging, referrals) are fully hidden when the feature flag is off, and fully functional when it is on. The existing test suite passes in both states.

## Naming and domains

Working name: **Lake City Commons**. As of 2026-07-19, `lakecitycommons.com` and `lakecitycommons.org` appear unregistered (no NS records, no whois records). Owner to register both; `.org` is the primary brand, `.com` redirects.

## Architecture

One Laravel 12 application (this repo) with two faces:

- **Public face** (no auth): digest/news posts, events calendar, organization directory, submission form. Built for SEO: clean URLs, Open Graph tags, sitemap, site RSS feed.
- **Private face** (existing referral-gated community): unchanged, but hidden behind a feature flag. `FEATURE_COMMUNITY=false` in `.env` → community routes return 404 and nav links do not render. Implemented as `config('features.community')` checked in a route-group middleware and in Blade nav partials.

**Infrastructure** (unchanged from PLAN.md): DreamHost shared hosting, MySQL, `database` queue driver with cron-based `queue:work --stop-when-empty`, file cache/session.

**New external services:**

- **Buttondown** — newsletter delivery, subscriber management, unsubscribes, and (Phase 3) paid subscriptions. Free tier at launch. The app pushes published digests via the Buttondown API. Bulk email is never sent from DreamHost.
- **Claude API** — drafts the weekly digest from aggregated items (`claude-sonnet-5`; estimated $1–5/month at weekly cadence). API key in `.env`.

## Content pipeline

### Source registry

`sources` table, managed from the admin panel:

```
id, name, url, type (enum: rss|ics|html|dataset),
selector_config (json nullable — CSS selectors for html type; dataset query params for dataset type),
organization_id (FK → organizations nullable), active (bool),
last_fetched_at, last_succeeded_at, consecutive_failures (int default 0),
timestamps
```

Source types at launch:

- **rss** — org blogs and Seattle-area news feeds (items filtered for Lake City relevance by the drafting step, not at fetch time)
- **ics** — org Google Calendars and similar; events flow directly to the public calendar
- **html** — org sites without feeds, scraped via per-source CSS selectors; kept to a minimum
- **dataset** — Seattle open-data endpoints (building permits, land use notices) filtered to Lake City geography via query parameters stored in `selector_config`

### Fetch job (weekly, Friday 22:00 cron)

`FetchSourcesJob` iterates active sources. Each type has a fetcher class implementing a common `SourceFetcher` interface (`fetch(Source): Collection<RawItem>`). Normalized results are stored in:

```
content_items:
id, source_id (FK), url, title, summary (text), content_hash (sha256 of normalized title+body),
kind (enum: news|event|notice), published_at (nullable), fetched_at,
status (enum: new|in_digest|ignored), timestamps
unique index (source_id, content_hash)
```

Deduplication: an item whose `(source_id, content_hash)` already exists is skipped. Per-source failures are caught, logged, and increment `consecutive_failures`; one bad source never aborts the run. The job is idempotent — safe to re-run.

ICS events additionally upsert into `events` (see Calendar below) with `status=approved` (structured data, low risk). HTML-scraped events upsert with `status=pending` for owner review.

### Drafting job (weekly, Saturday 06:00 cron)

`DraftDigestJob` collects the week's `status=new` content items and calls the Claude API to produce a digest draft in house style, organized into sections: **News**, **This Week's Events**, **Org Updates**, **City Notices**. Every claim links to its source URL. The prompt instructs: no invented facts, link everything, flag uncertain items with `[VERIFY]`.

The draft is stored as an unpublished post in the existing news-post feature (extended with `status: draft|review|published`, `published_at`, `newsletter_sent_at`). Items included get `status=in_digest`.

**The digest never auto-publishes.** The owner reviews, edits, and publishes.

**Fallback:** if the Claude API call fails (after 2 retries), the job creates the draft as a raw categorized item list (grouped by kind, with links) so the owner can assemble the digest manually that week. A dashboard notice explains what happened.

### Publish action

Publishing a digest post (admin button) atomically: sets `status=published` + `published_at`, then pushes the rendered digest to Buttondown via API and records `newsletter_sent_at`. If the Buttondown push fails, the post stays published on-site and the admin sees a "newsletter not sent — retry" button; retry is safe because `newsletter_sent_at` guards double-sends.

## Public modules

### Digest & news posts (`/news`, `/news/{slug}`)

Extends the existing admin news-post feature. Public index + detail pages, Open Graph meta, XML sitemap, site-wide RSS feed at `/feed`.

### Events calendar (`/events`)

```
events:
id, title, description (text), starts_at, ends_at (nullable), location (nullable), url (nullable),
organization_id (FK nullable), source_id (FK nullable), submission_id (FK nullable),
external_uid (nullable — ICS UID for upserts), status (enum: pending|approved|rejected),
timestamps
unique index (source_id, external_uid) where external_uid not null
```

List view (default, upcoming events) and month grid view; filter by organization. Each event links to its source org. ICS export at `/events.ics` so residents can subscribe in their own calendar apps.

### Organization directory (`/directory`)

```
organizations:
id, name, slug, category (enum: community|services|business|government),
description (text), website, email (nullable), phone (nullable), address (nullable),
is_sponsor (bool default false), sponsor_tier (nullable), active (bool),
timestamps
```

Logo via existing Spatie MediaLibrary. Categorized public listing; managed in admin. Doubles as sponsor-slot inventory for Phase 2.

### Community submissions (`/submit`)

Public form, no account required. Type: event or announcement.

```
submissions:
id, type (enum: event|announcement), submitter_name, submitter_email,
title, body (text), event_fields (json nullable — starts_at, location, url),
status (enum: pending|approved|rejected), ip_hash, timestamps
```

Spam control: honeypot field + rate limiting by IP (5/day). Approved event submissions create an `events` row; approved announcements become content items folded into the next digest draft. The owner approves/rejects from the same review queue as scraped events.

## Admin additions

- **Review queue** page: pending submissions, pending scraped events, and the current digest draft in one place — designed so the weekly routine is a single page visit.
- **Sources** CRUD with per-source health (last success, consecutive failures).
- **Dashboard warnings:** any source with `consecutive_failures >= 2` shows a warning banner and triggers a notification email to the owner (once per source per failure streak, not weekly spam).

## Monetization roadmap (phased)

1. **Launch → ~500 newsletter subscribers:** everything free; Buttondown free tier. Goal is trust and habit.
2. **Sponsors:** 2–4 local business slots ($50–100/month) in the digest footer and site sidebar. Implementation is template ad slots rendered from `organizations` where `is_sponsor=true`; payment via Stripe payment links (no code).
3. **Reader memberships:** Buttondown paid subscription tier (~$5/month supporter). Later, membership becomes the gate for the community features (time banking / item sharing) when `FEATURE_COMMUNITY` is enabled — a perk no other neighborhood site offers. Laravel Cashier is deliberately deferred until Buttondown's paid tier is outgrown.

## Error handling summary

- Per-source fetch failures: isolated, logged, counted; admin warning + one email after 2 consecutive weekly failures.
- Claude API failure: raw-item-list fallback draft; owner still publishes manually.
- Buttondown push failure: post stays live; guarded manual retry.
- All scheduled jobs idempotent.
- Public form abuse: honeypot, rate limit, all submissions held for review.

## Testing

- Existing suite must pass with `FEATURE_COMMUNITY` both on and off (flag toggled in tests).
- Feature tests: each fetcher type against fixture files (sample RSS/ICS/HTML/dataset responses), dedupe behavior, ICS event upsert, submission validation + spam controls, review-queue approval flows, publish action with mocked Buttondown API (including failure + retry guard), drafting job with mocked Claude API (including fallback path).
- No tests hit live external services.

## Out of scope for v1

- Enabling the community features (flag stays off)
- Stripe/Cashier integration
- Public reader accounts or comments
- Automated social-media cross-posting
- Mobile app

## Risks

- **Scraping fragility:** HTML sources break when sites redesign. Mitigated by source-health monitoring and by pushing orgs toward the submission form and ICS feeds over time.
- **Editorial trust:** an AI-drafted digest with a hallucinated claim damages the site's credibility. Mitigated by mandatory human review, source links on every claim, and the `[VERIFY]` flag convention.
- **Audience ramp:** revenue depends on consistent weekly publishing for months before sponsors sign. This is an owner-commitment risk, not a technical one; the design minimizes the weekly cost of consistency.
- **Name collision:** unrelated "Lake City Commons" complexes exist in other states; none in Seattle. Low risk.
