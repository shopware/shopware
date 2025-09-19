# Repository Pattern (Skeleton)

- Purpose: Provide uniform interface for entity CRUD & search
- Creation: `repositoryFactory.create(entityName)`
- Common methods (list placeholders): `search`, `get`, `save`, `delete`, `clone`
- Uses Criteria object to express queries
- Handles:
  - Mapping raw API responses to entity objects
  - Tracking changes (dirty state) via Entity Hydrator
- Identity & change tracking limitations (current pain points)
- Best practices:
  - Reuse same repository instance within component scope
  - Avoid deep nested loads unless needed (performance)
- Extension / decoration approach for plugins
- Future: stronger typing, improved diffing
