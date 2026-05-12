# Release Notes for Curated

## 1.0.0 - Unreleased

### Added
- Initial release
- `Curated` field type — supports Entries, Categories, Assets, Users, and (when Commerce is installed) Products & Variants, with native sub-source picker (Sections, Groups, Volumes, Product Types, …)
- **Auto-discovery** — the field automatically surfaces every element of the chosen target type that has any native relation to the parent, in either direction. No configuration required.
- Native element picker UX via `Cp::elementSelectHtml` — chip rendering, drag-to-reorder, add/remove, modal selector
- `viewMode` setting (`list` or `large`) for the field's picker
- Returns a native, chainable `ElementQuery`, e.g. `category.curatedProducts.all()`
- `curatedBy(source, fieldHandle)` ElementQuery behavior for querying the other side, e.g. `craft.products.curatedBy(category, 'curatedProducts').all()`
- **Curated Sync** utility (`Utilities → Curated Sync`) and `php craft curated/sync` console command — optional snapshot of currently-related elements into the explicit curated order
- Auto-cleanup of curated rows when their target element is deleted
- Install migration creating `{{%curated_relations}}` table with per-site ordering support
