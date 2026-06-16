[Docs](../README.md) › [Features](../README.md#features) › Reordering

# Reordering

Curated gives you several ways to get a list into the order you want — from dragging a handful of items to resorting hundreds at once.

## Drag to reorder

The primary interaction: grab an item and drag it into place. Save the element to persist the order. Order is stored [per source and per site](how-it-works.md#per-source-per-site).

## Quick reorder actions

Each item has a menu with quick actions, alongside Craft's native **Move up** / **Move down**:

- **Move to top**
- **Move to bottom**
- **Move to position N** — jump an item straight to a specific position without dragging through everything in between.

These are much faster than dragging on long lists.

## Pin items

**Pin** an item to always lead the list for that source. Pinned items:

- Stay at the top through drag-reorders and Sort by… operations.
- Stay pinned when new relations are auto-discovered and added to the list.

Pinning is per source, so an item can be pinned in one Category and unpinned in another. Use it for an always-first lead image, featured article, or headline product.

## One-shot "Sort by…"

A **Sort by…** dropdown sits above the picker. Pick a criterion and the whole list is resorted in one go:

- Title
- Date
- Random
- **Price** (Commerce Products and Variants)

This is a one-shot action — it rewrites the current order, it doesn't set an ongoing rule. Pinned items stay at the top. To set where *new* items land automatically, use [Default Placement](field-settings.md#default-placement) instead.

## Search

A live **search** input above the picker filters the visible items as you type. Non-matching items are hidden while their order is preserved underneath — nothing is reordered or removed. Designed for fields holding hundreds of items where scrolling to find one is slow.

---

[← How Curated works](how-it-works.md) · [Field settings →](field-settings.md)
