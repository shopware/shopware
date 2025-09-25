# Repository Pattern (Skeleton)

- Purpose: Provide uniform interface for entity CRUD & search
- Creation: `repositoryFactory.create(entityName)`
- Common methods (list placeholders): `search`, `get`, `save`, `delete`, `clone`
- Uses Criteria object to express queries
- Automatic TypeScript type generation
- Handles:
  - Mapping raw API responses to entity objects
  - Tracking changes (dirty state) via Entity Hydrator
- Best practices:
  - Reuse same repository instance within component scope
  - Avoid deep nested loads unless needed (performance)
  - Reload after save pattern
