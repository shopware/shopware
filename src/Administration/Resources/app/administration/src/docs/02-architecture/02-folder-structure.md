# Folder Structure (Skeleton)

- Top-level `src/Administration/Resources/app/administration/src` major folders:
  - `app/` – Vue app layer (entry points, bootstrap glue, router, initialization)
  - `core/` – Framework utilities (services, factories, helpers, mixins, state mgmt wrappers)
  - `meta/` – Test / metadata catalogs (datasets, identifiers, position indexes) (planned doc)
  - `module/` – Business domain modules (products, orders, customers, etc.)
- Additional notable folders (outside immediate `src` root to reference later)
  - `static/` or assets (if present) – (confirm & document)
  - Build config (webpack / vite? current stack details placeholder)
- Module internal typical structure:
  - `component/`, `page/`, `view/`, `acl/`, `snippet/`, `state/`, `service/`, `mixin/`
- Cross-cutting directories inside `core/service` (e.g., `api/*` for API services)
- Naming conventions:
  - `sw-` prefix for legacy components & abstractions
  - Future: `mt-` from Meteor library; transition strategy
- Rationale for separation `core` vs `module` vs `app`
- Where extension points live vs should not (avoid polluting `core`)
- Planned migration notes (Vue 3, composition utilities location)
- Diagram placeholder: layered architecture (App Shell → Core Framework → Modules → Components)
