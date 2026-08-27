# Field settings

A reference for every Curated setting — what it does, its default, and anything to watch out for. Field-level settings are configured when you create or edit the field; the plugin-level setting is on the Curated plugin's settings page.

## Element type

**Field setting.** The type of element this field orders: Entry, Category, Asset, User, or (with Commerce) Product / Variant. Set once when creating the field. See [Supported element types](../guides/supported-element-types.md).

## Sources

**Field setting. Optional.** Restrict which sources the target elements can come from — a Section for Entries, a Group for Categories/Users, a Volume for Assets, a Product Type for Products. Leave unrestricted to allow any.

## Populate from all elements

**Field setting. Default: off.**

By default a Curated field fills itself from elements *related to* the element being edited. That works for a Work Category listing its Work entries, but not for a Work index page: nothing relates to it, and relating every Work entry back to the page just to list them would be busywork.

Turn this **on** and the relation requirement is dropped — **Sources** alone defines the set. Every element in those sources appears in the field, and new ones are picked up automatically as they're created, while manual order, pinning and [Sort by…](reordering.md#one-shot-sort-by) all keep working.

**Sources can't be set to "All"** when this is on — it's the only thing bounding the list. Saving the field settings will fail with a validation error under the switch until you pick specific sources.

Every element in those sources is loaded into the field, so a section with hundreds of entries will be slow to edit. The [search box](reordering.md) above the picker helps, but consider whether the whole section really belongs in one field.

A field can never list its own element, so an index page sitting in the same section as the entries it lists won't appear inside itself.

### Removing an element

In the default relation mode, removing an item is soft — the underlying relation is still there, so it comes back on the next render unless you delete the relation (or turn on [Fully remove on delete](#fully-remove-on-delete)).

With **Populate from all elements** on there's no relation to delete, so removal is recorded instead: the element is remembered as excluded and auto-discovery skips it from then on. To put it back, turn on [Allow adding elements](#allow-adding-elements) and re-add it through the picker — the picker only offers elements that aren't already in the list, so it shows exactly what's been removed.

## Allow adding elements

**Field setting. Default: off.** Curated is ordering-first, so the picker's **Add** button is hidden by default — the list comes from auto-discovered relations. Turn this **on** to show the Add button and allow curated-only additions through the field.

With **Populate from all elements** on, this button doubles as the way to restore an element an editor removed.

## Default Placement

**Field setting.** Controls where an auto-discovered relation lands in the list *before* it has been explicitly ordered. See [How Curated works](how-it-works.md) for when this applies. Options:

- **Before** other elements
- **After** other elements
- **Title**
- **Date created**
- **Date updated**
- **Random**
- **Price** — Commerce Products and Variants only

Unlike the one-shot [Sort by…](reordering.md#one-shot-sort-by) action, Default Placement is an ongoing rule applied to new relations as they appear.

## Fully remove on delete

**Plugin setting. Default: off. Destructive.**

By default, removing an item from a Curated field is *soft*: it drops out of the saved order but reappears at the end on the next render, because the native relation still exists.

When **Fully remove on delete** is **on**, removing an item from a Curated field also deletes **every native relation row** between the two elements — in both directions, across any relation field. There is no undo.

> ⚠️ With this on, an editor removing an item here will silently edit the canonical relation elsewhere in Craft, not just this field. It's off by default for a reason. See [Large lists & limits](../guides/large-lists.md#warnings) for the full warning.
