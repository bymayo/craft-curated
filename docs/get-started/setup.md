[Docs](../README.md) › [Get started](../README.md#get-started) › Setup

# Setup

This walks through creating a Curated field, populating it, and rendering it on the front end. The examples assume a field with the handle `curatedProducts` on a **Category**, ordering **Commerce Products** — adapt the handles and element types to your own setup.

## 1. Create a Curated field

Go to **Settings → Fields → New field** and choose **Curated** as the field type.

- Give it a **Name** and **Handle** (e.g. `curatedProducts`).
- Pick the **Element type** you want to order: Entry, Category, Asset, User, or (with Commerce) Product / Variant.
- Optionally restrict **Sources** — for example, limit an Entry field to a single Section, or a Product field to one Product Type.
- Review the other [field settings](../features/field-settings.md) (Default Placement, Allow adding elements).

Save the field, then add it to the field layout of the **source** element — the element you'll be ordering *from*. In this example that's the Category.

> The source holds the order. If you want to order Products per Category, the Curated field goes on the **Category**, not the Product.

## 2. Open the source and reorder

Open a source element (e.g. a Category). The Curated field is already pre-populated with every element of the target type that's natively related to it — in either direction. You don't configure anything; it discovers the relations itself. See [How Curated works](../features/how-it-works.md).

Drag items into the order you want and **save**. For faster reordering on long lists, use the [quick reorder actions, pinning, Sort by… and search](../features/reordering.md).

## 3. Render on the front end

Read the field by its handle in Twig:

```twig
{% for product in category.curatedProducts.all() %}
    {{ product.title }}
{% endfor %}
```

That's it. For chaining query methods, querying from the other side, and GraphQL, see [Templating → Twig](../templating/twig.md) and [Templating → GraphQL](../templating/graphql.md).

## Verify it worked

- The field shows your related elements pre-populated (not empty) when you open a source that has native relations.
- After dragging and saving, reload the source — the order persists.
- The front-end loop above outputs items in your saved order.

If the field is empty when you expect items, confirm there's an actual native relation between the source and the target elements (Curated only *orders* existing relations — it doesn't create them). See [How Curated works](../features/how-it-works.md).

---

[← Requirements](requirements.md) · [Features overview →](../features/overview.md)
