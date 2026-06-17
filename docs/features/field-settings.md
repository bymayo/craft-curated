# Field settings

A reference for every Curated setting — what it does, its default, and anything to watch out for. Field-level settings are configured when you create or edit the field; the plugin-level setting is on the Curated plugin's settings page.

## Element type

**Field setting.** The type of element this field orders: Entry, Category, Asset, User, or (with Commerce) Product / Variant. Set once when creating the field. See [Supported element types](../guides/supported-element-types.md).

## Sources

**Field setting. Optional.** Restrict which sources the target elements can come from — a Section for Entries, a Group for Categories/Users, a Volume for Assets, a Product Type for Products. Leave unrestricted to allow any.

## Allow adding elements

**Field setting. Default: off.** Curated is ordering-first, so the picker's **Add** button is hidden by default — the list comes from auto-discovered relations. Turn this **on** to show the Add button and allow curated-only additions through the field.

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
