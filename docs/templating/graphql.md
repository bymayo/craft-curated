# GraphQL

Curated fields surface on their host element with the standard element argument set for the target type (`limit`, `offset`, `status`, `search`, `orderBy`, etc.). The resolver returns the saved curated order, then applies any arguments you pass.

## Querying

```graphql
{
  category(slug: "t-shirts") {
    curatedProducts(limit: 12, status: "live") {
      ... on Product {
        id
        title
      }
    }
  }
}
```

## Mutations

Mutations accept an array of element IDs in the desired curated order:

```graphql
mutation {
  save_someSection_someEntryType_Entry(
    id: 1308
    curatedProducts: [1639, 1660, 1657]
  ) {
    id
  }
}
```

The order of the IDs in the array becomes the curated order.
