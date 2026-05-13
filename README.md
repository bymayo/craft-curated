<img src="https://raw.githubusercontent.com/bymayo/craft-curated/craft-5/resources/icon.png" width="60">

# Curated for Craft CMS 5

Craft can show you related elements, but can't reorder them when the relation lives on the other side. **Curated fixes that.** Add a Product to your *T-shirts* category and it shows up automatically in *T-shirts'* Curated field, ready to drag into place. The same Product can sit at #1 in *T-shirts* and #9 in *Sale*, each category with its own independent order. Works with Entries, Categories, Assets, Users, and (with Craft Commerce) Products and Variants.

## Perfect for…

- **Categories with products**: reorder products per category, move popular items to the top.
- **Editorial / blog landing pages**: "Related articles", "Editor's picks", "More from this author" in a deliberate sequence.
- **Image galleries**: order images per Album, lead image first.
- **Event lineups**: order speakers per conference, artists per festival, or sessions per day.
- **Series and courses**: drag episodes into the right sequence per series, modules per course, or chapters per book.

## Features

- **Per-source sort order**: the same Entry can be #1 on one Category page and #9 on another. Same goes for Products in storefront categories, Assets in galleries, or Users on different team pages.
- **Auto-discovery**: every native relation between the source and the target type surfaces in the field, in either direction, no configuration.
- **Default Placement**: where auto-discovered relations land before they're explicitly ordered. Before or after other elements, title, date created/updated, random, plus **price** for Commerce Products and Variants.
- **Quick reorder actions**: Move to top / bottom / position N inside each item's menu, alongside Craft's Move up / Move down.
- **Pin items**: pin an item to the top of the list from its action menu. Pinned items always lead, regardless of subsequent sorts, drag-reorders, or newly auto-discovered relations. Pinned items render with a blue marker icon and a faint blue tint so they stand out. Toggle off via the same menu (`Unpin`). Pin state is per source — pinning a product in *T-shirts* doesn't pin it in *Sale*.
- **One-shot Sort by…**: dropdown above the picker for resorting the whole list (title, date, random, price).
- **Search**: live filter input above the picker. Hides non-matching items while preserving order, for fields holding hundreds of items.
- **Ordering-first by default**: the Add button is hidden; flip **Allow adding elements** on the field to enable curated-only additions.
- **Six element types**: Entries, Categories, Assets, Users, Commerce Products and Variants. Narrow by source (Section, Group, Volume, Product Type).
- **Native Twig**: `category.curatedProducts.all()` returns a chainable `ElementQuery`.
- **Per-site ordering**: different order per site.
- **Element-index column**: each Curated field is a column option on its host element's index. Shows the first curated item with a "+N" overflow for the rest, exactly like Craft's native relation field columns.

## How Curated compares

Curated is about **ordering**, not establishing or proxying the relation itself.

| Capability                                                | Craft's native relation fields | Many to Many            | Reverse Relations       | Curated                       |
|-----------------------------------------------------------|--------------------------------|-------------------------|-------------------------|-------------------------------|
| Drag-reorder per source                                   | ❌ shared sortOrder            | ❌ uses native          | ❌ uses native          | ✅                            |
| Same target at different positions in different sources   | ❌                             | ❌                      | ❌                      | ✅                            |
| Show relations created from the *other* side              | ❌                             | ✅ one configured field | ✅ one configured field | ✅ any field, either direction |
| Field's picker writes a native relation                   | ✅                             | ✅ proxies              | varies                  | ❌ ordering only              |
| Per-site ordering                                         | ❌                             | inherits native         | inherits native         | ✅                            |
| Auto-includes new relations created elsewhere             | ❌                             | ❌                      | ❌                      | ✅                            |
| Quick reorder actions (Move to top / bottom / position)   | ❌                             | ❌                      | ❌                      | ✅                            |
| Pin / Unpin items to the top of a source                  | ❌                             | ❌                      | ❌                      | ✅                            |
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

1. Create a **Curated** field. Give it a **handle** (e.g. `curatedProducts`), pick the **Element type** (Entry, Category, Asset, User, Commerce Product / Variant), optionally restrict **Sources**, then add it to the field layout of the source element (e.g. a Category).
2. Open the source. The field is pre-populated with every matching element already natively related to it. Drag to reorder; save.
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

The second argument is the handle of the Curated field on the source. Returns empty if the field doesn't exist on that layout.

## How Curated works

Curated queries every native relation between this source and elements of the target type, in either direction:

- Category has an Entries field pointing at entries (source → target), **or**
- Entry has a Categories field pointing at the category (target → source).

Both surface in the Curated field. The displayed list is `[saved curated order] + [native relations not yet curated]`. **Default Placement** controls where new natives land.

### Sync utility

Go to **Utilities → Curated Sync** in the CP and click **Sync now**.

You don't need this for Curated fields to work, they populate themselves. Run it when you want to save the current order in one go, for example after importing a batch of entries or migrating content between environments. It's safe to re-run, nothing gets duplicated.

### Console command

```sh
php craft curated/sync
```

Same as the Sync utility, just from the terminal. Handy after a deploy, or from a script.

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

1. **Curated sits alongside native relations.** Keep the canonical relation where Craft expects it; use Curated on the source for order.
2. **Removing an item is soft by default.** It drops out of the saved curated order, but reappears at the end on next render because the native relation still exists. To remove for good, also remove the native relation. Or enable the **Fully remove on delete** plugin setting below.
3. **Fully remove on delete (plugin setting) is destructive.** When on, removing an item from a Curated field also deletes every native relation row between the two elements, in both directions, across any relation field. There's no undo. Editors removing items here will silently edit the canonical relation elsewhere in Craft, not just this field. Off by default for a reason.
4. **Curated writes content, not Project Config.** Field settings sync via project config; the curated order itself lives in `curated_relations` and won't sync between environments.
5. **Large lists.** The drag UI is good for hundreds of items, not tens of thousands. See the `max_input_vars` note below if you expect 1000+ items per field.

When an element is deleted entirely, it's removed from every curated list automatically.

### `max_input_vars` and big lists

PHP's `max_input_vars` (default `1000`) caps how many form inputs a request can have. Craft's element picker emits one input per item, so lists over ~1000 items will silently lose items on save unless you raise the limit:

```ini
max_input_vars = 5000
post_max_size = 16M
```

## Support

If you have any issues then I'll aim to reply as soon as possible. If it's a site-breaking-oh-no-what-has-happened moment, hit me up on the Craft CMS Discord, @bymayo
