# Release Notes for Curated

## 1.0.0 - 2026-05-12

### Added
- Initial release.
- `Curated` field type. Supports Entries, Categories, Assets, Users, and (with Craft Commerce) Products and Variants, with a sources picker (Sections, Groups, Volumes, Product Types).
- **Auto-discovery.** The field automatically surfaces every element of the chosen target type that has any native relation to the source, in either direction. No configuration required.
- Native element picker UX via `Cp::elementSelectHtml`. Chip rendering, drag-to-reorder, modal selector, native action menu.
- **View Mode** field setting. List, Inline list, Cards, Card grid. Visual radio picker matching Craft's native Entries field.
- **Default Placement** field setting. Controls where auto-discovered relations land before they're explicitly curated. Before / after other elements, title A–Z / Z–A, date created (newest / oldest), date updated, random, plus **Price** (low / high) when the target is a Commerce Product or Variant.
- **Quick reorder actions** in each chip's action menu. Move to top, Move to bottom, Move to position N, alongside Craft's native Move up / Move down.
- **Pin / Unpin** in the chip action menu. Pinned items always render first regardless of subsequent sorts or auto-discovery; a small marker is shown on pinned chips. Pin state is stored alongside the curated order in `curated_relations`.
- **Inline search input** above the picker. Filters the displayed chips by label (client-side) for fields holding hundreds of items. Order is preserved; non-matching chips are hidden, not removed.
- **Inline "Sort by…" dropdown** above the picker. One-shot resort of the displayed list with a confirmation prompt to prevent misclicks.
- **Inline search input** above the picker. Filters the displayed chips by label (client-side) for fields holding hundreds of items. Order is preserved; non-matching chips are hidden, not removed.
- **Allow adding elements** field setting (off by default). Hides the picker's add button so the field stays in sync with native relations. Flip on to allow editors to add curated-only items.
- **"Add" Button Label** field setting. Custom label for the picker's add button. Defaults to the target type's native label ("Add an entry", "Add a category", etc.).
- **Fully remove on delete** plugin setting (off by default). When on, removing a chip also deletes the underlying native relation in both directions.
- **Notice** plugin setting. Subtle help text rendered below every Curated field. Editable per site, configurable via `config/curated.php`.
- **Curated Sync utility** (`Utilities → Curated Sync`) and **`php craft curated/sync`** console command. Snapshots currently-related elements into the explicit curated order. Safe to re-run.
- Twig: returns a native chainable `ElementQuery` (e.g. `category.curatedProducts.all()`).
- `curatedBy(source, fieldHandle)` ElementQuery behavior for querying from the other side (e.g. `craft.products.curatedBy(category, 'curatedProducts').all()`).
- Per-site ordering. Different curated order per site in multi-site setups.
- Auto-cleanup of curated rows when their target element is deleted.
- **Element-index column for every Curated field.** Each Curated field is a column option on its host element's index, named after the field. Shows the first curated item as a chip and overflows the rest into a "+N" pill (the same widget Craft uses for its own relation field columns).
- English translation file at `src/translations/en/curated.php`.
- Install migration creating the `{{%curated_relations}}` table with foreign keys to fields, elements, and sites.
