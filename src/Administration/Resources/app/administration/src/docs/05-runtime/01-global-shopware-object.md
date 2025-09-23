# Global Shopware Object (Skeleton)

- Purpose: Central registry + facade for core extensibility (historical design)
- Contains registries for:
  - Components
  - Directives / Filters
  - Services / Factories
  - Mixin registry
  - State (store modules)
  - Feature flag accessor
  - ...
- Creation timing: during boot before view mount
- Pros:
  - Simple access for plugin authors
  - Globally available without injection
- Cons:
  - Large global surface (risk of misuse)
  - Harder tree-shaking / dead code elimination
  - Tight coupling