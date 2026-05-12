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
- **Customizable editor notice**: plugin setting; subtle help text rendered under every Curated field.

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

Pick by use case:
- Set up relations from the "wrong" side → Many to Many / Reverse Relations.
- Order existing relations per parent → Curated.
- Both → use them together.

## Install

```sh
composer require bymayo/curated
```

Enable in `Settings > Plugins`, or install via the Plugin Store.

## Requirements

- Craft CMS 5.7+
- PHP 8.2+

## Setup

1. Add a **Curated** field to the parent element (e.g. Category). Pick the **Element type** (Entry, Category, Asset, User, Commerce Product / Variant) and optionally restrict **Sources**.
2. Open the parent. The field is pre-populated with every matching element already natively related to this parent. Drag to reorder; save.
3. Read on the front end via the field handle:

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

## Recipes

### Curated first, then everything else

Editors curate top picks; the rest fall back to a natural order.

```twig
{% set curated = category.curatedProducts.all() %}
{% set curatedIds = curated|map(p => p.id) %}
{% set rest = craft.products
    .relatedTo(category)
    .id(['not', curatedIds])
    .orderBy('postDate desc')
    .all() %}

{% for product in curated|merge(rest) %}
    {{ product.title }}
{% endfor %}
```

### Top N curated picks

```twig
{% set featured = category.curatedProducts.limit(3).all() %}
```

## How auto-discovery works

Curated queries every native relation between this parent and elements of the target type, in either direction:

- Category has an Entries field pointing at entries (parent → target), **or**
- Entry has a Categories field pointing at the category (target → parent).

Both surface in the Curated field. The displayed list is `[saved curated order] + [native relations not yet curated]`. **Default Placement** controls where new natives land.

### Sync utility

**Utilities → Curated Sync** (and `php craft curated/sync`) snapshots currently-related elements into explicit curated order. Optional; auto-discovery already shows them.

## Order of operations

| Action | What it does |
|---|---|
| Drag a chip | Rearranges DOM. Persists on save. |
| Move up / Move down | Single-step move. Persists on save. |
| Move to top / bottom / position… | Big-step move. Persists on save. |
| Sort by… dropdown | Confirms, then reorders the whole list. Persists on save. |
| Removing a chip | Drops it from `curated_relations` on save. Reappears at the end if the native relation still exists. |

Whatever's in the picker when you save **is** the new curated order. Default Placement only governs how brand-new natives show up.

## Supported element types

| Element type | Common use case |
|---|---|
| Entry | "Related articles", "Editor's picks" |
| Category | Sub-categories under a parent |
| Asset | Gallery / lookbook order |
| User | Featured authors, staff order |
| Commerce Product | Drag products inside a category |
| Commerce Variant | Variant display order |

Commerce types appear when Craft Commerce is installed. Each type narrows by its native source (Sections for Entries, Groups for Categories, Volumes for Assets, Product Types for Products).

## Caveats

1. **Curated sits alongside native relations.** Keep the canonical relation where Craft expects it; use Curated on the parent for order.
2. **Removing a natively-related chip is soft.** It reappears at the end on next render because the native relation still exists. To remove for good, remove the native relation.
3. **Curated writes content, not Project Config.** Field settings sync via project config; the order itself lives in `curated_relations` and doesn't.
4. **Large lists.** The drag UI is good for hundreds of items, not tens of thousands.

When an element is deleted, it's removed from every curated list automatically.

### `max_input_vars` and big lists

PHP's `max_input_vars` (default `1000`) caps how many form inputs a request can have. Craft's element picker emits one input per chip, so lists over ~1000 items will silently lose items on save unless you raise the limit:

```ini
max_input_vars = 5000
post_max_size = 16M
```

## Support

If you have any issues then I'll aim to reply as soon as possible. If it's a site-breaking-oh-no-what-has-happened moment, hit me up on the Craft CMS Discord, @bymayo
