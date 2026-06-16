[Docs](../README.md) › [Guides](../README.md#guides) › Large lists & limits

# Large lists & limits

## Warnings

1. **Curated sits alongside native relations.** Keep the canonical relation where Craft expects it; use Curated on the source for order. See [How Curated works](../features/how-it-works.md).
2. **Removing an item is soft by default.** It drops out of the saved curated order, but reappears at the end on next render because the native relation still exists. To remove for good, also remove the native relation — or enable [**Fully remove on delete**](../features/field-settings.md#fully-remove-on-delete).
3. **Fully remove on delete is destructive.** When on, removing an item from a Curated field also deletes every native relation row between the two elements, in both directions, across any relation field. There's no undo. Editors removing items here will silently edit the canonical relation elsewhere in Craft, not just this field. Off by default for a reason.
4. **Curated writes content, not Project Config.** Field settings sync via project config; the curated order itself lives in `curated_relations` and won't sync between environments. See [Sync](../features/sync.md).
5. **Large lists.** The drag UI is good for hundreds of items, not tens of thousands. See `max_input_vars` below if you expect 1000+ items per field.

When an element is deleted entirely, it's removed from every curated list automatically.

## `max_input_vars` and big lists

PHP's `max_input_vars` (default `1000`) caps how many form inputs a request can have. Craft's element picker emits one input per item, so lists over ~1000 items will silently lose items on save unless you raise the limit:

```ini
max_input_vars = 5000
post_max_size = 16M
```

---

[← Supported element types](supported-element-types.md) · [Changelog →](../resources/changelog.md)
