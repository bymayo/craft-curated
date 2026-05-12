<img src="https://raw.githubusercontent.com/bymayo/craft-curated/craft-5/src/icon.svg" width="60">

# Curated for Craft CMS 5

Curated lets editors **manually order related elements per parent**. The same target can sit at position 1 in one parent and position 9 in another, which Craft's native relations table can't do.

## Why

Craft's `relations` table stores `sortOrder`, but it's keyed on the *source* of the relation. If Products have a Categories field, the order is "this product's categories", not "this category's products." Curated stores per-parent order in its own join table, so every Category keeps its own independent order.

## Perfect for…

- **Categories with products**: reorder products per category, move popular items to the top.
- **Editorial / blog landing pages**: "Related articles", "Editor's picks", "More from this author" in a deliberate sequence.
- **Galleries / lookbooks**: drag Asset thumbnails into a hero-first order per Album.
- **Staff / contributor pages**: order Users on a Team entry by seniority or department.
- **Tag-driven feeds**: promote handpicked entries per tag, surface the rest automatically.

## Features

- **Per-parent sort order**: the same Product can be #1 in *T-shirts* and #9 in *Sale*.
- **Auto-discovery**: every native relation between the parent and the target type surfaces in the field, in either direction, no configuration.
- **Default Placement**: where auto-discovered relations land before they're explicitly ordered. Before or after other elements, title, date created/updated, random, plus **price** for Commerce Products and Variants.
- **Quick reorder actions**: Move to top / bottom / position N inside each chip's menu, alongside Craft's Move up / Move down.
- **One-shot Sort by…**: dropdown above the picker for resorting the whole list (title, date, random, price).
- **Ordering-first by default**: the Add button is hidden; flip **Allow adding elements** on the field to enable curated-only additions.
- **Six element types**: Entries, Categories, Assets, Users, Commerce Products and Variants. Narrow by source (Section, Group, Volume, Product Type).
- **Native Twig**: `category.curatedProducts.all()` returns a chainable `ElementQuery`.
- **Per-site ordering**: different order per site.

## How Curated compares

Curated is about **ordering**, not establishing or proxying the relation itself.

| Capability                                                | Craft's native relation fields | Many to Many            | Reverse Relations       | Curated                       |
|-----------------------------------------------------------|--------------------------------|-------------------------|-------------------------|-------------------------------|
| Drag-reorder per parent                                   | ❌ shared sortOrder            | ❌ uses native          | ❌ uses native          | ✅                            |
| Same target at different positions in different parents   | ❌                             | ❌                      | ❌                      | ✅                            |
| Show relations created from the *other* side              | ❌                             | ✅ one configured field | ✅ one configured field | ✅ any field, either direction |
| Field's picker writes a native relation                   | ✅                             | ✅ proxies              | varies                  | ❌ ordering only              |
| Per-site ordering                                         | ❌                             | inherits native         | inherits native         | ✅                            |
| Auto-includes new relations created elsewhere             | ❌                             | ❌                      | ❌                      | ✅                            |
| Quick reorder actions (Move to top / bottom / position)   | ❌                             | ❌                      | ❌                      | ✅                            |
| Inline "Sort by…" reorder (title / date / random / price) | ❌                             | ❌                      | ❌                      | ✅                            |
| Default Placement for new relations                       | ❌                             | ❌                      | ❌                      | ✅                            |

**Different jobs.** Many to Many and Reverse Relations *edit the inverse side* of one specific relation field. Curated *orders* whatever's already related (any direction, any field).

## Install

```sh
composer require bymayo/curated
```

Enable in `Settings > Plugins`, or install via the Plugin Store.

## Requirements

- Craft CMS 5.7+
- PHP 8.2+

## Setup

1. Add a **Curated** field to the parent element (e.g. Category). Give it a **handle** (e.g. `curatedProducts`), pick the **Element type** (Entry, Category, Asset, User, Commerce Product / Variant), and optionally restrict **Sources**.
2. Open the parent. The field is pre-populated with every matching element already natively related to this parent. Drag to reorder; save.
3. Read on the front end via your field handle. The examples below assume the handle is `curatedProducts`:

```twig
{% for product in category.curatedProducts.all() %}
    {{ product.title }}
{% endfor %}
```

Chain any normal query method:

```twig
{% set top3 = category.curatedProducts.limit(3).all() %}
{% set inStock = category.curatedProducts.status('live').all() %}
```

### Query from the other side

```twig
{% set products = craft.products.curatedBy(category, 'curatedProducts').all() %}
```

The second argument is the handle of the Curated field on the parent. Returns empty if the field doesn't exist on that layout.

## How Curated works

Curated queries every native relation between this parent and elements of the target type, in either direction:

- Category has an Entries field pointing at entries (parent → target), **or**
- Entry has a Categories field pointing at the category (target → parent).

Both surface in the Curated field. The displayed list is `[saved curated order] + [native relations not yet curated]`. **Default Placement** controls where new natives land.

### Sync utility

Go to **Utilities → Curated Sync** in the CP and click **Sync now**. This snapshots every currently-related element into `curated_relations` so its position is explicit, persisted, and no longer dependent on Default Placement.

You don't need to run it for the field to work. Reach for it when you want to:

- Lock in the existing order across all Curated fields after a migration or bulk import, so future native relations land at the bottom (or wherever Default Placement says) instead of mixing with established items.
- Freeze the current view as a baseline before changing the Default Placement setting.
- Reset to a known state after large data changes.

It's idempotent. Items already in `curated_relations` aren't moved or duplicated.

### Console command

```sh
php craft curated/sync
```

Same operation as the Sync utility, just from the terminal. Useful in CI pipelines, post-deploy hooks, or any scripted environment where opening the CP isn't practical.

## Supported element types

| Element type | Common use case |
|---|---|
| Entry | "Related articles", "Editor's picks" |
| Category | Sub-categories under a parent |
| Asset | Gallery / lookbook order |
| User | Featured authors, staff order |
| Commerce Product | Drag products inside a category |
| Commerce Variant | Variant display order |

Craft Commerce is required for Commerce Product and Variant types.

## Warnings

1. **Curated sits alongside native relations.** Keep the canonical relation where Craft expects it; use Curated on the parent for order.
2. **Removing an item is soft by default.** It drops out of the saved curated order, but reappears at the end on next render because the native relation still exists. To remove for good, also remove the native relation. Or enable the **Fully remove on delete** plugin setting below.
3. **Fully remove on delete (plugin setting) is destructive.** When on, removing an item from a Curated field also deletes every native relation row between the two elements, in both directions, across any relation field. There's no undo. Editors removing items here will silently edit the canonical relation elsewhere in Craft, not just this field. Off by default for a reason.
4. **Curated writes content, not Project Config.** Field settings sync via project config; the curated order itself lives in `curated_relations` and won't sync between environments.
5. **Large lists.** The drag UI is good for hundreds of items, not tens of thousands. See the `max_input_vars` note below if you expect 1000+ chips per field.

When an element is deleted entirely, it's removed from every curated list automatically.

### `max_input_vars` and big lists

PHP's `max_input_vars` (default `1000`) caps how many form inputs a request can have. Craft's element picker emits one input per chip, so lists over ~1000 items will silently lose items on save unless you raise the limit:

```ini
max_input_vars = 5000
post_max_size = 16M
```

## Support

If you have any issues then I'll aim to reply as soon as possible. If it's a site-breaking-oh-no-what-has-happened moment, hit me up on the Craft CMS Discord, @bymayo
