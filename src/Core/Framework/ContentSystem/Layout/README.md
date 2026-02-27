# Layout

Content layout tree structure and processing. Layouts are reusable templates containing nested content elements with slots.

## Architecture

1. **Element Structure** (Element/) — ContentElement tree with slots, visitor pattern for traversal
2. **DAL Definitions** (Entity/, Field/) — Database schema and custom field serializers
3. **Loading** (Loader/) — ContentLayoutEntity retrieval from repository
4. **Scaffolding** (Scaffolding/) — Layout wrapping utilities (virtual root wrapper)

## Multi-Root Layouts

ContentLayoutEntity can contain multiple root elements. Each root is an independent tree with separate context scope — providers in one root CANNOT provide to elements in another root. Element IDs must be unique across all roots for partial rendering.

## Subdirectories

- **Element/** - ContentElement tree structure, visitor pattern, context and data requirement definitions
- **Entity/** - DAL definitions (ContentLayoutDefinition)
- **Field/** - Custom DAL field types and serializers (infrastructure)
- **Loader/** - ContentLayoutEntity loading from repository
- **Scaffolding/** - Layout wrapping utilities (VirtualRootWrapper)
