# Release Notes for Curate

## 1.0.0 - Unreleased

### Added
- Initial release
- `Curated Relations` field type — polymorphic, supports any element type registered with Craft (Entries, Categories, Assets, Users, Tags, Commerce Products & Variants, …) with native sub-source picker (Sections, Groups, Volumes, Product Types, …)
- Native element picker UX via `Cp::elementSelectHtml` — chip rendering, drag-to-reorder, add/remove, modal selector — matches the look and feel of Craft's built-in relation fields
- `viewMode` setting (`list` or `large`) for the field's picker
- Returns a native, chainable `ElementQuery`, e.g. `category.curatedProducts.all()`, `entry.curatedAssets.limit(6).all()`
- `curatedBy(source, fieldHandle)` ElementQuery behavior for querying the other side, e.g. `craft.products.curatedBy(category, 'curatedProducts').all()`
- `Curate` service with `getTargetIds`, `saveOrder`, `append`, `remove`
- Plugin settings for `autoAppendNewItems` and `pruneOnRelationRemoved` (CP + `config/curate.php` + env vars)
- Install migration creating `{{%curate_relations}}` table with per-site ordering support
