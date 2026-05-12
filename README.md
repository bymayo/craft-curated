<img src="https://github.com/bymayo/craft-curate/blob/craft-5/resources/icon.png" width="60">

# Curate for Craft CMS 5

Curate lets editors **manually order related elements per parent** — drag products into your preferred sequence inside a Category, drag entries inside an Author, drag anything inside anything. The same target can sit at position 1 in one parent and position 9 in another, which is the bit Craft's native relations table can't do.

## Why

Craft's `relations` table stores a `sortOrder`, but it's keyed on the *source* of the relation. If your Products have a Categories field, the order is "this product's categories", not "this category's products". The KB article ([Manually Sorting Commerce Products](https://craftcms.com/knowledge-base/manually-sorting-commerce-products)) gets around this by stuffing a Products field on a Global Set — which works, but only gives you one global order.

Curate stores per-parent order in its own join table, so every Category (or any parent) keeps its own independent product order.

## Features

- **Per-parent sort order** — the same Product can be #1 in *T-shirts* and #9 in *Sale*
- **One field, any element type** — Entries, Categories, Assets, Users, Tags, Commerce Products, anything Craft knows about; pick the type *and* the sub-source (Section, Group, Volume, …) at field config time
- **Curated Relations field** — drop onto any element with a field layout
- **Native Twig access** — `category.curatedProducts.all()` returns an `ElementQuery`, fully chainable
- **Per-site ordering** — different order per site if you want it
- **Settings via `config.php` or env vars**

## Install

- Install with Composer via `composer require bymayo/curate` from your project directory
- Enable / install the plugin in the Craft Control Panel under `Settings > Plugins`
- Add a **Curated Relations** field to the parent element (e.g. Category) and set the target element type

You can also install via the Plugin Store by searching for **Curate**.

## Requirements

- Craft CMS 5.x
- PHP 8.2+

## Setup

1. **Add the field.** Edit the field layout of your *parent* element (e.g. Category) and add a **Curated Relations** field.
   - Give it a **handle** — e.g. `curatedProducts`, `curatedEntries`, `curatedAssets`. You'll access this in Twig.
   - Pick an **Element type** — Entry, Category, Asset, User, Tag, Commerce Product, or anything else registered as an element type.
   - Optionally tick **Sources** to limit the picker (e.g. only entries from the *News* section, only assets from the *Gallery* volume, only users from the *Customers* group). Leave all unchecked for "any source".
2. **Order in the CP.** Open the parent element. Drag the children into your preferred order. Save.
3. **Read on the front end.** The field returns a native `ElementQuery`, so it works like any other field:

   ```twig
   {% set category = craft.categories.slug('t-shirts').one() %}

   {% for product in category.curatedProducts.all() %}
       {{ product.title }}
   {% endfor %}
   ```

   You can chain anything you'd chain on a normal query:

   ```twig
   {% set top3 = category.curatedProducts.limit(3).all() %}
   {% set inStock = category.curatedProducts.status('live').all() %}
   ```

### Alternative: query from the other side

If you have a child element type and want to *find* the curated order by a parent (e.g. you have a category ID but not the loaded element), use the `curatedBy()` query method:

```twig
{% set products = craft.products
    .curatedBy(category, 'curatedProducts')
    .all() %}
```

The second argument is the handle of the Curated Relations field on the parent. If the field doesn't exist on the parent's field layout, the query returns an empty result.

## Recipes

### Curated first, then everything else

The realistic pattern for product listings: editors curate the *top picks*, and the rest fall back to a natural order. No need to drag every product into position.

```twig
{% set category = craft.categories.slug('t-shirts').one() %}

{# 1. Curated products in the editor's explicit order #}
{% set curated = category.curatedProducts.all() %}
{% set curatedIds = curated|map(p => p.id) %}

{# 2. Everything else still related to the category, in your fallback order #}
{% set rest = craft.products
    .relatedTo(category)
    .id(['not', curatedIds])
    .orderBy('postDate desc')
    .all() %}

{# 3. Merge — curated first, then the rest #}
{% for product in curated|merge(rest) %}
    {{ product.title }}
{% endfor %}
```

If nothing's been curated yet, `curated` is empty and `rest` returns every related product in your fallback order — so the page never breaks during rollout.

### Top N curated picks only

```twig
{% set featured = category.curatedProducts.limit(3).all() %}
```

### Curated per site

The order is stored per site by default, so the same Twig works in multi-site setups — editors can curate different orders per locale.

## What about transparent `relatedTo()`?

By design, Curate doesn't intercept native `relatedTo()` queries — your existing relations keep working unchanged, and the curated order is opt-in via either `parent.curatedHandle` or `craft.entries.curatedBy(...)`. Transparent interception is on the roadmap behind a setting.

## Config

You can configure the plugin via the Control Panel under `Settings > Plugins > Curate`, or via a `config/curate.php` file (with env var support):

```php
<?php

return [
    'autoAppendNewItems' => true,
    'pruneOnRelationRemoved' => true,
];
```

| Setting | Default | What it does |
|---|---|---|
| `autoAppendNewItems` | `true` | When a target element is newly linked to a parent via its native relation, append it to the end of the curated order automatically. |
| `pruneOnRelationRemoved` | `true` | Remove curated entries when the underlying native relation is removed. |

## Supported element types

Anything registered with Craft's element type registry — the field's Element type dropdown is populated dynamically, so anything new your plugins add (Commerce, Calendar, Campaign, custom element types) just shows up.

Out of the box this covers:

| Element type | Common use case |
|---|---|
| Entry | Curate "Related articles" on a News entry, "Editor's picks" on a landing page |
| Category | Curate sub-categories under a parent |
| Asset | Curate a gallery / lookbook / image carousel order |
| User | Curate "Featured authors" or staff order on a Team page |
| Tag | Curate tag display order |
| Commerce Product | The headline use case — drag products inside a category |
| Commerce Variant | Curate variant display order |

Each element type is narrowed by its native source concept: Sections / Entry Types for Entries, Category Groups for Categories, Volumes for Assets, User Groups for Users, Product Types for Products, and so on.

## Caveats

1. **The default relation field still works.** Curate doesn't replace native relations — it sits alongside them. The intent is: keep the canonical relation where Craft expects it (e.g. on the Product), and use Curate on the parent to express per-parent order.
2. **Curate writes content, not Project Config.** Field settings are in Project Config (correct). The curated order itself is content and lives in the `curate_relations` table — do not expect it to sync between environments via project config.
3. **Large lists.** The current drag-reorder UI is appropriate for hundreds of items, not tens of thousands. If you need to curate huge catalogues, raise an issue.

## Support

If you have any issues then I'll aim to reply as soon as possible. If it's a site-breaking-oh-no-what-has-happened moment, hit me up on the Craft CMS Discord — @bymayo

## Roadmap

- Transparent `relatedTo()` interception (opt-in via setting)
- Auto-sync via `EVENT_AFTER_SAVE` on the target element
- Permissions per field for who can reorder
- Bulk reorder helpers (move to top, move to position N) for long lists
- Console command for bulk import / export of curated order
- Element index "Curated by" column to show where a target appears
