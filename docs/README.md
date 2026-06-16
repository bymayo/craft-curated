<img src="https://raw.githubusercontent.com/bymayo/craft-curated/craft-5/resources/icon.png" width="60">

# Curated Documentation

Craft can show you related elements, but can't reorder them when the relation lives on the other side.

**Curated fixes that.** The field auto-populates from existing relations — add a Product to your *T-shirts* category and it appears in *T-shirts'* Curated field, ready to drag into place. The same Product can sit at #1 in *T-shirts* and #9 in *Sale*, each category with its own independent order. Works with Entries, Categories, Assets, Users, and (with Craft Commerce) Products and Variants.

> ### 🎬 &nbsp;[Watch the video walkthrough →](https://www.youtube.com/watch?v=Vzwgn6WafNg)
> See Curated in action in under a few minutes.

---

## Contents

### Get started
- [Installation](get-started/installation.md) — install via Composer or the Plugin Store
- [Requirements](get-started/requirements.md) — Craft and PHP versions
- [Setup](get-started/setup.md) — create your first Curated field and render it

### Features
- [Overview](features/overview.md) — everything Curated does, at a glance
- [How Curated works](features/how-it-works.md) — auto-population and the displayed list
- [Reordering](features/reordering.md) — drag, quick actions, pin, Sort by…, search
- [Field settings](features/field-settings.md) — every setting, what it does, and its caveats
- [Element index column](features/element-index.md) — show curated items on the element index
- [Sync](features/sync.md) — the Sync utility and console command

### Templating
- [Twig](templating/twig.md) — query the field, chain methods, query from the other side
- [GraphQL](templating/graphql.md) — queries and mutations

### Guides
- [Use cases](guides/use-cases.md) — worked examples for common scenarios
- [How Curated compares](guides/comparison.md) — vs. native relations, Many to Many, Reverse Relations
- [Supported element types](guides/supported-element-types.md) — what you can curate
- [Large lists & limits](guides/large-lists.md) — warnings and `max_input_vars`

### Resources
- [Changelog](resources/changelog.md)
- [Support](resources/support.md)

---

## Perfect for…

- **Categories with products**: reorder products per category, move popular items to the top.
- **Editorial / blog landing pages**: "Related articles", "Editor's picks", "More from this author" in a deliberate sequence.
- **Image galleries**: order images per Album, lead image first.
- **Event lineups**: order speakers per conference, artists per festival, or sessions per day.
- **Series and courses**: drag episodes into the right sequence per series, modules per course, or chapters per book.

See [Use cases](guides/use-cases.md) for worked examples.

<img src="https://raw.githubusercontent.com/bymayo/craft-curated/craft-5/resources/screenshot.png" width="850">
