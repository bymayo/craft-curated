[Docs](../README.md) › [Templating](../README.md#templating) › Twig

# Twig

A Curated field returns a chainable element query, so it behaves like any native relation field in your templates. The examples assume the field handle is `curatedProducts` on a Category.

## Read the field

```twig
{% for product in category.curatedProducts.all() %}
    {{ product.title }}
{% endfor %}
```

`category.curatedProducts` returns an `ElementQuery` in the saved curated order.

## Chain query methods

Chain any normal query method:

```twig
{% set top3 = category.curatedProducts.limit(3).all() %}
{% set inStock = category.curatedProducts.status('live').all() %}
```

## Query from the other side

To get the curated order from the *target* element's query (the other direction), use `curatedBy()`:

```twig
{% set products = craft.products.curatedBy(category, 'curatedProducts').all() %}
```

- The first argument is the **source** element that holds the order.
- The second argument is the **handle** of the Curated field on that source.

Returns empty if the field doesn't exist on that source's field layout.

---

[← Sync](../features/sync.md) · [GraphQL →](graphql.md)
