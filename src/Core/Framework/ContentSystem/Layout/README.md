# Layout

Content layout tree structure and processing. Layouts are reusable templates containing nested content elements with slots.

## Architecture

1. **Element Structure** (Element/) — ContentElement tree with slots, visitor pattern for traversal
2. **DAL Definitions** (Entity/, Field/) — Database schema and custom field serializers
3. **Loading** (Loader/) — ContentLayoutEntity retrieval from repository
4. **Scaffolding** (Scaffolding/) — Layout wrapping utilities (virtual root wrapper)
5. **Default Seeding** (`LayoutDefaultSeeder`) seeds element-type primitive defaults into the stored tree at the DAL write boundary, invoked from the `Field/` layout serializer's `normalize` hook

## Default Seeding

`LayoutDefaultSeeder` walks an element forest and, per node, fills each primitive property of the node's `component` type whose default is non-null and whose key is absent, recursing into every slot's children. It never overwrites an existing value and no-ops on an unregistered `component`. It handles both payload shapes the layout field serializer carries (`ContentElement` objects and raw element arrays from Admin / Sync JSON) and shares the per-type rule (`Type/PrimitiveDefaultProvider`) with the layout mutations. Running it at the `ContentElementListFieldSerializer::normalize` write seam means every tree reaching `content_layout` through the DAL carries its type defaults, including paths that bypass the `Mutation/` ops (direct DAL write, Sync API, import, fixtures). Raw-SQL and migration writes go through `Connection`, not the DAL, so they bypass this seam.

## Multi-Root Layouts

ContentLayoutEntity can contain multiple root elements. Each root is an independent tree with separate context scope — providers in one root CANNOT provide to elements in another root. Element IDs must be unique across all roots for partial rendering.

## Subdirectories

- **Element/** - ContentElement tree structure, visitor pattern, context and data requirement definitions
- **Entity/** - DAL definitions (ContentLayoutDefinition)
- **Field/** - Custom DAL field types and serializers (infrastructure)
- **Loader/** - ContentLayoutEntity loading from repository
- **Scaffolding/** - Layout wrapping utilities (VirtualRootWrapper)
- **Type/** - Element type system: declarative type definitions, YAML loading, registry, app integration
