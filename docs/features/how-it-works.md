# How Curated works

Curated queries every native relation between this source and elements of the target type, in either direction:

- Category has an Entries field pointing at entries (source → target), **or**
- Entry has a Categories field pointing at the category (target → source).

Both surface in the Curated field. The displayed list is:

```
[saved curated order] + [native relations not yet curated]
```

So anything you've explicitly ordered comes first in the order you set, followed by any newly discovered relations that haven't been placed yet. [**Default Placement**](field-settings.md#default-placement) controls where those new natives land.

## Ordering, not relating

Curated is about **ordering** — it doesn't establish or proxy the relation itself. The picker doesn't write a native relation; it records order. Keep the canonical relation where Craft expects it (on whichever field already holds it), and use Curated on the source to order it.

This is why removing an item is soft by default, and why a field can appear empty even though you expect items in it. See [Large lists & limits](../guides/large-lists.md) for the removal and deletion behaviour, and [How Curated compares](../guides/comparison.md) for how this differs from Many to Many / Reverse Relations.

## Per source, per site

Order is stored **per source** and **per site**. The same target element can hold a different position in every source it's related to, and a different order in each site.
