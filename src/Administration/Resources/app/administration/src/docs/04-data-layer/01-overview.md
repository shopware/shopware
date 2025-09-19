# Data Layer Overview (Skeleton)

- Goals: Abstract backend DAL entities, provide consistent CRUD, reduce boilerplate
- Core pieces:
  - Repository Factory
  - Entity Schemas / Definitions
  - Criteria API (filters, sorting, associations)
  - API Services (custom endpoints beyond generic entity routes)
- Data flow baseline diagram placeholder
- Caching / identity map strategy (current vs desired)
- Error propagation & retry patterns (to elaborate)
- Extension points: adding custom fields/entities, extending repository behavior
- Relationship to state management (when to persist in store vs local)
- Future improvements: batching, optimistic updates, offline queue (ideas)
