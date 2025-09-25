# Module System (Skeleton)

- Definition: Self-contained business domain package within administration
- Responsibilities per module:
  - Register routes / pages / navigation entries
  - Provide components & views
  - Define state (Pinia modules) & services
  - Supply ACL privileges & snippet translations
  - Optionally contribute to search, ...
- Registration mechanism:
  - Module factory / registry (document API surface)
  - Lifecycle hooks: before/after registration, route guards
- Isolation goals:
  - Minimize cross-module coupling (interactions via services / repositories)
  - Clear ownership by solution teams
- Naming conventions and folder layout