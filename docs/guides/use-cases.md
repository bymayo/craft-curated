[Docs](../README.md) › [Guides](../README.md#guides) › Use cases

# Use cases

Curated shines anywhere the relation already exists but the *order* matters — and where that order should differ depending on which element you're looking at. Here are common scenarios with the setup and a front-end snippet for each.

## Categories with products

Reorder products per category and move popular items to the top. The same product can rank differently in *T-shirts* and *Sale*.

**Setup:** Curated field (`curatedProducts`, element type Product) on the **Category**.

```twig
{% for product in category.curatedProducts.all() %}
    {{ product.title }}
{% endfor %}
```

## Editorial / blog landing pages

"Related articles", "Editor's picks", or "More from this author" in a deliberate sequence rather than newest-first.

**Setup:** Curated field (`relatedArticles`, element type Entry) on the landing/section element.

```twig
{% for article in entry.relatedArticles.limit(4).all() %}
    {{ article.title }}
{% endfor %}
```

## Image galleries

Order images per Album, with the lead image first.

**Setup:** Curated field (`galleryImages`, element type Asset) on the Album entry. [Pin](../features/reordering.md#pin-items) the lead image to keep it first.

```twig
{% for image in album.galleryImages.all() %}
    {{ image.getImg() }}
{% endfor %}
```

## Event lineups

Order speakers per conference, artists per festival, or sessions per day.

**Setup:** Curated field (`lineup`, element type Entry or User) on the event element.

## Series and courses

Drag episodes into the right sequence per series, modules per course, or chapters per book.

**Setup:** Curated field (`episodes`, element type Entry) on the series element.

```twig
{% for episode in series.episodes.all() %}
    {{ loop.index }}. {{ episode.title }}
{% endfor %}
```

---

[← GraphQL](../templating/graphql.md) · [How Curated compares →](comparison.md)
