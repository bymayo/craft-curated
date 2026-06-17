# How Curated compares

Curated is about **ordering**, not establishing or proxying the relation itself.

| Capability                                                | Craft's native relation fields | Many to Many            | Reverse Relations       | Curated                       |
|-----------------------------------------------------------|--------------------------------|-------------------------|-------------------------|-------------------------------|
| Drag-reorder per source                                   | ❌ shared sortOrder            | ❌ uses native          | ❌ uses native          | ✅                            |
| Same target at different positions in different sources   | ❌                             | ❌                      | ❌                      | ✅                            |
| Show relations created from the *other* side              | ❌                             | ✅ one configured field | ✅ one configured field | ✅ any field, either direction |
| Field's picker writes a native relation                   | ✅                             | ✅ proxies              | varies                  | ❌ ordering only              |
| Per-site ordering                                         | ❌                             | inherits native         | inherits native         | ✅                            |
| Auto-includes new relations created elsewhere             | ❌                             | ❌                      | ❌                      | ✅                            |
| Quick reorder actions (Move to top / bottom / position)   | ❌                             | ❌                      | ❌                      | ✅                            |
| Pin / Unpin items to the top of a source                  | ❌                             | ❌                      | ❌                      | ✅                            |
| Inline "Sort by…" reorder (title / date / random / price) | ❌                             | ❌                      | ❌                      | ✅                            |
| Default Placement for new relations                       | ❌                             | ❌                      | ❌                      | ✅                            |
| GraphQL support                                           | ✅                             | ❌                      | ❌                      | ✅                            |

**Different jobs.** Many to Many and Reverse Relations *edit the inverse side* of one specific relation field. Curated *orders* whatever's already related (any direction, any field).

This is why Curated's picker doesn't write a native relation — see [How Curated works](../features/how-it-works.md). Pair Curated with whichever field already holds the canonical relation.
