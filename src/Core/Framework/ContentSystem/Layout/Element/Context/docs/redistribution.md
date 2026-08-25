# Context Redistribution

The `redistribute` flag on an `acceptsContext` entry, which lets a container pass the context it receives straight through to its own children.

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

Both produce identical results. The container automatically passes data to all children.

The broadcast provider that `redistribute: true` stands for is generated at runtime from `ContextConsumer::$redistribute` by the redistribute derivation in `Rendering/WiringPlanner::plan()`, before the render step; it is never persisted with the layout. The dotted-key rule and the `providesContext`-coexistence rule below are enforced one step earlier, by the wiring validation in that same `plan()` call, which judges the whole authored forest — including a subtree a partial render is about to discard. The `consumerAlias` requires `redistribute: true` rule is enforced separately, at decode (`Layout/Codec/StoredElementCodec`) and by the write-time constraints (`Layout/Codec/StoredTreeConstraints`).

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

Container accepts `featuredProduct`, children receive `product`. Reuse the same product card components everywhere.

**Constraints:**

- `consumerAlias` on `acceptsContext` requires `redistribute: true`. Without redistribution, a consumer alias has no effect and will cause a validation error.
- `redistribute: true` cannot be used with dotted context keys (e.g., `"product.cover": {"redistribute": true}` is invalid). Use full `providesContext` configuration for nested path redistribution.
- `redistribute: true` cannot coexist with an explicit `providesContext` entry for the same key on the same element.

**Property Alias vs Consumer Alias:**

- `consumerAlias` (in `providesContext`): Provider renames context for all children receiving it
- `consumerAlias` (in `acceptsContext`): Redistributed context is exposed to children under this name (requires `redistribute: true`)
- `propertyAlias` (in `acceptsContext`): Individual consumer renames context for its own use only (does NOT require `redistribute`)

A `propertyAlias` renames the storage key on the consuming element; it cannot contain dots and must be unique within that element.

Use `consumerAlias` when all children need the same rename. Use `propertyAlias` when individual consumers need different internal names.

## Choosing Your Approach

**Use `redistribute: true` for simple pass-through:**
- Container elements that just pass data to children unchanged
- All children need the same data (automatic broadcast)
- Quick setup with minimal configuration

**Use full `providesContext` configuration for advanced scenarios:**
- Different distribution strategies (indexed, keyed, sliced, iterator) - see [Distribution Strategies](distribution-strategies.md)
- Need specific nested properties like `product.cover`
- Transforming or splitting data before passing to children

## Reusable Components in Nested Layouts

**Real-world scenario:** You build a product card component that shows title, price, and image. This card should work whether placed directly on a page, inside a grid, within a section, or nested in a slider. Each container just needs to pass the product data through.

**Build once, use anywhere:** Redistribution cascades through multiple container levels automatically. Your reusable components work in any context without reconfiguration.

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

The `content-section` container automatically passes product data to nested components. Move this section to different pages - it still works.
