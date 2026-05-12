<img src="https://raw.githubusercontent.com/bymayo/craft-curated/craft-5/src/icon.svg" width="60">

# Curated for Craft CMS 5

Curated lets editors **manually order related elements per parent** — drag products into your preferred sequence inside a Category, drag entries inside an Author, drag anything inside anything. The same target can sit at position 1 in one parent and position 9 in another, which is the bit Craft's native relations table can't do.

## Why

Craft's `relations` table stores a `sortOrder`, but it's keyed on the *source* of the relation. If your Products have a Categories field, the order is "this product's categories", not "this category's products". The KB article ([Manually Sorting Commerce Products](https://craftcms.com/knowledge-base/manually-sorting-commerce-products)) gets around this by stuffing a Products field on a Global Set — which works, but only gives you one global order.

Curated stores per-parent order in its own join table, so every Category (or any parent) keeps its own independent product order.

## Features

- **Per-parent sort order** — the same Product can be #1 in *T-shirts* and #9 in *Sale*
- **Auto-discovery** — every native relation between the parent and an element of the chosen type surfaces in the field, in either direction, with no configuration
- **Initial sort** — field setting for how auto-discovered items appear before they're explicitly ordered: title, date created, date updated, random, or "place at bottom"
- **Quick reorder** — per-chip "Move to top / bottom / position N" menu for long lists where drag is impractical
- **One field, six element types** — Entries, Categories, Assets, Users, and (when Commerce is installed) Products & Variants; narrow by source (Section, Group, Volume, Product Type, …) at field config time
- **Native Twig access** — `category.curatedProducts.all()` returns an `ElementQuery`, fully chainable
- **Per-site ordering** — different order per site if you want it
- **Scales to large lists** — chip submissions bundled into a single input so PHP's `max_input_vars` is never the bottleneck

## How Curated compares

Several Craft plugins live in or near the "let editors control relations" space. Each makes different trade-offs, and Curated is about **ordering**, not about establishing or proxying the relation itself.

| Capability                                              | Plain Craft        | Many to Many       | Reverse Relations  | Curated                       |
|---------------------------------------------------------|--------------------|--------------------|--------------------|-------------------------------|
| Drag-reorder per parent                                 | ❌ shared sortOrder | ❌ uses native     | ❌ uses native     | ✅                            |
| Same target at different positions in different parents | ❌                 | ❌                 | ❌                 | ✅                            |
| Show relations created from the *other* side           | ❌                 | ✅ one configured field | ✅ one configured field | ✅ any field, either direction |
| Field's picker writes a native relation                 | ✅                 | ✅ proxies         | varies             | ❌ — picker affects curated order only |
| Per-site ordering                                       | n/a                | inherits native    | inherits native    | ✅                            |
| Auto-includes new relations created elsewhere           | n/a                | ❌                 | ❌                 | ✅                            |

**Different jobs, not direct replacements.** Many to Many and Reverse Relations focus on *editing the inverse side* of one specific relation field — a "this Category has these Entries" picker that writes to the Entries' Categories field. Curated focuses on *order*: it doesn't manage the underlying relation, it surfaces whatever is already related (any direction, any field) and lets editors drag them into per-parent order.

Pick by use case:

- **Set up relations from the "wrong" side of the field** → Many to Many or Reverse Relations.
- **Order existing relations per parent, irrespective of which field created them** → Curated.
- **Both** → use them together. Many to Many for editing, Curated on the parent for ordering.

## Install

- Install with Composer via `composer require bymayo/curated` from your project directory
- Enable / install the plugin in the Craft Control Panel under `Settings > Plugins`
- Add a **Curated** field to the parent element (e.g. Category) and set the target element type

You can also install via the Plugin Store by searching for **Curated**.

## Requirements

- Craft CMS 5.x
- PHP 8.2+

## Setup

1. **Add the field.** Edit the field layout of your *parent* element (e.g. Category) and add a **Curated** field.
   - Give it a **handle** — e.g. `curatedProducts`, `curatedEntries`, `curatedAssets`. You'll access this in Twig.
   - Pick an **Element type** — Entry, Category, Asset, User, Commerce Product, or Commerce Variant.
   - Pick **Sources**, or leave **All** ticked to allow any source.
2. **That's it for setup.** Open the parent element — the Curated field is already populated with every element of the chosen type that's natively related to this parent (any direction, any relation field). Drag to reorder, save.
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

The second argument is the handle of the Curated field on the parent. If the field doesn't exist on the parent's field layout, the query returns an empty result.

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

By design, Curated doesn't intercept native `relatedTo()` queries — your existing relations keep working unchanged, and the curated order is opt-in via either `parent.curatedHandle` or `craft.entries.curatedBy(...)`. Transparent interception is on the roadmap behind a setting.

## How auto-discovery works

The Curated field doesn't need to be configured to track a specific relation field. At read time it queries every native relation between this parent and elements of the configured target type — in either direction:

- Category has an Entries field pointing at entries (parent → target), **or**
- Entry has a Categories field pointing at the category (target → parent)

Both surface in the Curated field on the Category.

The displayed list is `[curated order, in saved order] + [native relations not yet in curated]`. So the first time you open a Category that already had related entries, they're all there — no setup, no backfill button. Drag to reorder; that order is what persists. New native relations made elsewhere (saving an Entry with this Category in its Categories field) show up the next time the Curated field is rendered.

### Curated Sync utility (optional)

There's also a **Utilities → Curated Sync** page (and a `php craft curated/sync` console command) that snapshots all currently-related elements into the explicit curated order. You usually don't need it — auto-discovery is already showing them — but it's useful if you want to lock in current positions so they don't move around when new natives are added.

## Supported element types

| Element type | Common use case |
|---|---|
| Entry | Curate "Related articles" on a News entry, "Editor's picks" on a landing page |
| Category | Curate sub-categories under a parent |
| Asset | Curate a gallery / lookbook / image carousel order |
| User | Curate "Featured authors" or staff order on a Team page |
| Commerce Product | The headline use case — drag products inside a category |
| Commerce Variant | Curate variant display order |

Commerce types only appear when Craft Commerce is installed.

Each element type is narrowed by its native source concept: Sections / Entry Types for Entries, Category Groups for Categories, Volumes for Assets, User Groups for Users, Product Types for Products, and so on.

## Caveats

1. **The default relation field still works.** Curated doesn't replace native relations — it sits alongside them. The intent is: keep the canonical relation where Craft expects it (e.g. on the Product), and use Curated on the parent to express per-parent order.
2. **Removing a natively-related element from the Curated field is soft.** It drops out of your saved order, but reappears at the end on the next render because the native relation still exists. To remove it for good, remove the native relation.
3. **Source-filter on natives is best-effort.** If you've restricted Sources on the Curated field, the picker filters as you'd expect, but auto-discovered natives are returned regardless of source. Items outside the configured sources still appear in the field's value.
4. **Curated writes content, not Project Config.** Field settings are in Project Config (correct). The curated order itself is content and lives in the `curated_relations` table — do not expect it to sync between environments via project config.
5. **Large lists.** The current drag-reorder UI is appropriate for hundreds of items, not tens of thousands. If you need to curate huge catalogues, raise an issue.

When an element is deleted, it's removed from every curated list automatically so the order doesn't carry dangling IDs.

### `max_input_vars` and big lists

PHP's `max_input_vars` (default `1000`) caps the number of form inputs a request can contain. Craft's native element picker — which this plugin uses for the chip UI — emits one hidden input per selected chip, so a Curated field with 1000+ items would normally lose items on save.

To avoid that, Curated bundles every chip ID into a single JSON-encoded hidden input at submit time (the original chip inputs are disabled by JS so they don't count). One input regardless of size — `max_input_vars` is not a factor.

If you're still seeing issues — for example, a list so large that the JSON payload exceeds `post_max_size`, or JavaScript is disabled — raise the relevant PHP limits in `php.ini`:

```ini
max_input_vars = 5000
post_max_size = 16M
```

## Support

If you have any issues then I'll aim to reply as soon as possible. If it's a site-breaking-oh-no-what-has-happened moment, hit me up on the Craft CMS Discord — @bymayo

## Roadmap

- Transparent `relatedTo()` interception (opt-in via setting)
- Auto-sync via `EVENT_AFTER_SAVE` on the target element
- Permissions per field for who can reorder
- Bulk reorder helpers (move to top, move to position N) for long lists
- Console command for bulk import / export of curated order
- Element index "Curated by" column to show where a target appears
