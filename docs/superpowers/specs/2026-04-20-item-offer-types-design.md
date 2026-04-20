# Item Offer Types — Gift vs. Lend Design

**Date:** 2026-04-20
**Scope:** Items only. Skills are unchanged. Adds explicit offer type (gift vs. lend), exchange rate for lend items, return tracking, and item archiving for gifted items.
**Out of scope:** Skill offer types, partial returns, lend renewals/extensions.

---

## 1. Core Concepts

| Concept | Meaning |
|---|---|
| **Gift** | Permanent transfer. Borrower keeps the item. No return expected. No time credits. |
| **Lend** | Temporary. Item must come back. Exchange rate is set by owner. |
| **Exchange Rate** | Applies to lend only. Options: Free (no credits) / Time Equal / Custom. |
| **Archived** | Item that was gifted and claimed. Hidden from browse; visible only to original owner. |

---

## 2. Data Model

### 2.1 `items` table — new columns

**`offer_type`** `enum('gift', 'lend')` not null, default `'lend'`

Existing rows: items with `credit_type = 'gift'` migrate to `offer_type = 'gift'`; all others default to `offer_type = 'lend'`.

**`is_archived`** `boolean` not null, default `false`

Set to `true` automatically when a gift request is completed. Never set manually.

### 2.2 `credit_type` — semantics shift for items

`credit_type` column is unchanged (`gift | time_equal | custom`). Meaning by context:

| `offer_type` | `credit_type` | UI label | Credits move? |
|---|---|---|---|
| `gift` | `gift` | — (N/A) | No |
| `lend` | `gift` | Free | No |
| `lend` | `time_equal` | Time | Yes |
| `lend` | `custom` | Custom | Yes |

For `offer_type = gift`, `credit_type` is always set to `'gift'` and not shown in UI.

### 2.3 `requests` table — new status value

Add `returned` to the `status` enum:

```
enum('pending','accepted','in_progress','completed','declined','cancelled','returned')
```

Full lend lifecycle:
```
pending → accepted → in_progress → completed → returned
```

- `completed`: borrower has the item; credits (if any) have transferred; item `is_available = false`
- `returned`: owner confirmed return; item `is_available = true`

Gift lifecycle (unchanged):
```
pending → accepted → in_progress → completed
```
On `completed`: item `is_available = false`, `is_archived = true`.

---

## 3. Availability Logic

Item availability is system-managed on request state transitions. Owners cannot manually toggle `is_available` while an active lend is in flight.

| Condition | `is_available` | Visible in browse? |
|---|---|---|
| Listed, no active request | `true` | Yes |
| Lend request: `in_progress` or `completed` | `false` | No |
| Lend request: `returned` | `true` | Yes |
| Gift request: `completed` | `false` + `is_archived = true` | No (owner only) |
| Manually toggled off by owner (no active request) | `false` | No |

Browse queries add: `WHERE is_archived = false`.

---

## 4. State Machine Changes

### 4.1 New transition: `completed → returned`

- **Who can trigger:** Owner only
- **Condition:** Request `resource_type = 'item'`, item `offer_type = 'lend'`, current status = `completed`
- **Effect:** Status → `returned`; item `is_available = true`
- **UI:** "Mark as Returned" button on request detail page (owner view only, lend items, status = completed)

### 4.2 Existing transition: `in_progress → completed` (lend item)

- Effect unchanged: credits transfer (if applicable)
- Added effect: item `is_available = false` (system sets, not toggleable by owner)

### 4.3 Existing transition: `in_progress → completed` (gift item)

- Added effects: item `is_available = false`, item `is_archived = true`

---

## 5. UI Changes

### 5.1 Item create/edit form

Replace the current `credit_type` dropdown with a two-level choice:

**Step 1 — How are you offering this?**
```
○ Lend  — I want it back
○ Gift  — Keep it, it's yours
```

**Step 2 — Exchange rate** (shown only when Lend is selected)
```
○ Free        — No time credits
○ Time        — 1 hr = 1 credit
○ Custom      — Set a specific credit value
```

When Gift is selected: `offer_type = gift`, `credit_type = gift`, exchange rate step hidden.

### 5.2 Request detail page (owner view)

When `resource_type = 'item'`, item `offer_type = 'lend'`, request `status = 'completed'`:

Show a prominent "Mark as Returned" button. On click → POST to transition endpoint with `status = returned`. Button disappears once returned.

### 5.3 Owner's item list

Archived items appear at the bottom with:
- Muted styling (opacity reduced)
- "Archived — Gifted" badge
- No availability toggle
- No edit link

### 5.4 Browse / public listings

Filter: `WHERE is_archived = false AND is_available = true` (unchanged for is_available, add is_archived check).

---

## 6. Migration Strategy

Existing items are migrated in a single migration:

```sql
-- Add columns
ALTER TABLE items ADD COLUMN offer_type ENUM('gift','lend') NOT NULL DEFAULT 'lend';
ALTER TABLE items ADD COLUMN is_archived BOOLEAN NOT NULL DEFAULT FALSE;

-- Migrate existing gift items
UPDATE items SET offer_type = 'gift' WHERE credit_type = 'gift';
```

No data loss. Existing lend-like items (time_equal, custom) default to `offer_type = lend`. Existing gift items become `offer_type = gift`.

---

## 7. Files Changed / Created

| File | Change |
|---|---|
| `database/migrations/xxxx_add_offer_type_to_items.php` | New — add `offer_type`, `is_archived`; migrate existing data |
| `database/migrations/xxxx_add_returned_to_requests_status.php` | New — add `returned` to status enum |
| `app/Models/Item.php` | Add `offer_type`, `is_archived` to fillable and casts |
| `app/Models/ExchangeRequest.php` | Add `returned` to status documentation/constants |
| `app/Services/RequestService.php` | Add `completed → returned` transition; hook item availability updates |
| `app/Http/Controllers/ItemController.php` | Update store/update to handle offer_type + two-level form |
| `app/Http/Requests/StoreItemRequest.php` | New — validation for `offer_type` + conditional `credit_type` (lend only) |
| `app/Http/Requests/UpdateItemRequest.php` | New — same as above |
| `resources/views/items/create.blade.php` | Two-level offer type form |
| `resources/views/items/edit.blade.php` | Same |
| `resources/views/requests/show.blade.php` | "Mark as Returned" button for lend items |
| `resources/views/items/index.blade.php` | Archived items section at bottom |
| Browse views (skills/items browse) | Add `is_archived = false` filter |
