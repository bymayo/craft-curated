# Features overview

Everything Curated does, at a glance. Each item links to a deeper page where there is one.

- **Per-source sort order** — the same Entry can be #1 on one Category page and #9 on another. Same goes for Products in storefront categories, Assets in galleries, or Users on different team pages. See [How Curated works](how-it-works.md).
- **Auto-populates** — the field fills itself from every native relation between the source and target type, in either direction. No configuration. See [How Curated works](how-it-works.md).
- **Default Placement** — where auto-discovered relations land before they're explicitly ordered: before or after other elements, title, date created/updated, random, plus **price** for Commerce Products and Variants. See [Field settings](field-settings.md).
- **Quick reorder actions** — Move to top / bottom / position N inside each item's menu, alongside Craft's Move up / Move down. See [Reordering](reordering.md).
- **Pin items** — pin items to always lead the list, per source. Survives sorts, drag-reorders, and new auto-discovered relations. See [Reordering](reordering.md).
- **One-shot Sort by…** — dropdown above the picker for resorting the whole list (title, date, random, price). See [Reordering](reordering.md).
- **Search** — live filter input above the picker. Hides non-matching items while preserving order, for fields holding hundreds of items. See [Reordering](reordering.md).
- **Ordering-first by default** — the Add button is hidden; flip **Allow adding elements** on the field to enable curated-only additions. See [Field settings](field-settings.md).
- **Six element types** — Entries, Categories, Assets, Users, Commerce Products and Variants. Narrow by source (Section, Group, Volume, Product Type). See [Supported element types](../guides/supported-element-types.md).
- **Native Twig** — `category.curatedProducts.all()` returns a chainable `ElementQuery`. See [Twig](../templating/twig.md).
- **GraphQL** — query the field on its host element with all the usual element arguments (`limit`, `status`, `search`, etc.), and mutate it with a list of IDs in your desired curated order. See [GraphQL](../templating/graphql.md).
- **Per-site ordering** — different order per site.
- **Element-index column** — each Curated field is a column option on its host element's index. Shows the first curated item with a "+N" overflow for the rest, exactly like Craft's native relation field columns. See [Element index column](element-index.md).
