# Data Layer Overview (Skeleton)

- Goals: Abstract backend DAL entities, provide consistent CRUD, reduce boilerplate
- Core pieces:
  - Repository Factory
  - Entity Schemas / Definitions
  - Criteria API (filters, sorting, associations)
  - API Services (custom endpoints beyond generic entity routes)
- Error handling
- Extension points: adding custom fields/entities, extending repository behavior
- Relationship to state management (when to persist in store vs local)
