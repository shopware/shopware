# Context Redistribution

**The Problem:** You build reusable layout components (product cards, content blocks, sliders) that need to work in different places - homepage grids, category listings, search results. When you nest these components inside container elements (grids, sections, columns), the container needs to pass data through to the nested components.

**Example scenario:** A product grid contains product cards. The grid receives product data and needs to pass it to each card. Without redistribution, you must configure both `acceptsContext` (to receive data) AND `providesContext` (to pass it along) on the grid - verbose and repetitive.

**The Solution:** Use `redistribute: true` to automatically pass context through container elements.

**Comparison:**

```json
// Without redistribution - manual configuration (verbose)
"acceptsContext": {"product": {"type": "single", "required": true}},
"providesContext": {"product": {"type": "single", "distribution": "broadcast"}}

// With redistribution - automatic pass-through (concise)
"acceptsContext": {"product": {"type": "single", "required": true, "redistribute": true}}
```

Both produce identical results.

See [Where each rule is enforced](#where-each-rule-is-enforced) for where each rule above is enforced.

## Consumer Alias with Redistribution

You can rename the context key when redistributing. Useful when your reusable component expects different naming than what it receives.

**Example:** Container receives `featuredProduct`, but child product cards expect `product`:

```json
"acceptsContext": {
  "featuredProduct": {
    "type": "single",
    "required": true,
    "redistribute": true,
    "consumerAlias": "product"
  }
}
```

Container accepts `featuredProduct`, children receive `product`.

**Constraints:**

- `consumerAlias` on `acceptsContext` requires `redistribute: true`. Without redistribution, a consumer alias is rejected.
- `redistribute: true` cannot be used with dotted context keys (e.g., `"product.cover": {"redistribute": true}` is invalid). Use full `providesContext` configuration for nested path redistribution.
- `redistribute: true` cannot coexist with an explicit `providesContext` entry for the same key on the same element.
- `redistribute: true` cannot be combined with `scope: "root"`: [context-wiring.md](../../../../docs/principles/context-wiring.md#context-flows-only-between-adjacent-elements).

**Property Alias vs Consumer Alias:**

- `consumerAlias` (in `providesContext`): Provider renames context for all children receiving it
- `consumerAlias` (in `acceptsContext`): Redistributed context is exposed to children under this name (requires `redistribute: true`)
- `propertyAlias` (in `acceptsContext`): Individual consumer renames context for its own use only (does NOT require `redistribute`)

Use `consumerAlias` when all children need the same rename. Use `propertyAlias` when individual consumers need different internal names.

## Choosing Your Approach

**Use `redistribute: true` for simple pass-through:**
- Container elements that just pass data to children unchanged
- All children need the same data (automatic broadcast)

**Use full `providesContext` configuration for advanced scenarios:**
- Different distribution strategies (indexed, keyed, sliced, iterator) - see [Distribution Strategies](distribution-strategies.md)
- Need specific nested properties like `product.cover`
- Transforming or splitting data before passing to children

## Reusable Components in Nested Layouts

**Real-world scenario:** You build a product card component that shows title, price, and image. This card should work whether placed directly on a page, inside a grid, within a section, or nested in a slider. Each container just needs to pass the product data through.

Redistribution cascades through multiple container levels automatically.

**Example:** Product page > content section > product card > title element

```json
{
  "id": "product-page",
  "providesContext": {"product": {"type": "single", "distribution": "broadcast"}},
  "slots": {
    "main": [{
      "id": "content-section",
      "acceptsContext": {"product": {"type": "single", "required": true, "redistribute": true}},
      "slots": {
        "content": [{
          "id": "product-title",
          "acceptsContext": {"product.name": {"type": "single", "required": true}}
        }]
      }
    }]
  }
}
```

The `content-section` container automatically passes product data to nested components.

## Where each rule is enforced

`redistribute: true` makes `Rendering/WiringPlanner::plan()` add a derived provider at render time. It is never persisted.

Three sites judge wiring. The decoder `Layout/Codec/StoredElementWiringDecoder` (composed by `StoredElementCodec`) throws on decode. The descriptor `Layout/Codec/StoredTreeWiringConstraints` (composed by `StoredTreeConstraints`) reports a write-descriptor violation. `WiringPlanner::plan()` judges the forest before the [partial prune](../../../../docs/principles/README.md#glossary).

- `consumerAlias` without `redistribute`: the decoder throws `ContentSystemException::consumerAliasWithoutRedistribute()`; the descriptor reports on `[consumerAlias]`.
- `propertyAlias` with a dot: the decoder and the descriptor reject it.
- `propertyAlias` collision (base-key uniqueness): the base key is the first segment of `propertyAlias ?? contextKey`, split by `ConsumerBaseKeyResolver` so every site calls one split. The decoder throws `ContentSystemException::propertyAliasCollision()`; the descriptor reports every colliding consumer on its own `acceptsContext` entry; the planner judges it too. On the DAL write path the decode throw in `StoredElementListFieldSerializer::normalize()` precedes the constraint pass, so the descriptor fires only where the constraints run without a prior decode.
- `redistributeWithDottedPath` and `redistributeConflict` (the derived provider key `propertyAlias ?? contextKey` is one an authored provider already holds; a `consumerAlias` renames only what children match on and never enters that comparison): the decoder throws, the descriptor reports on `acceptsContext[<contextKey>][redistribute]`, the planner judges both.
- `rootScopeWithRedistribute`: the decoder throws, the descriptor reports on `acceptsContext[<contextKey>][scope]`, the planner judges it. `scope: root` with a `consumerAlias` is rejected transitively, because `consumerAlias` requires `redistribute: true`.
