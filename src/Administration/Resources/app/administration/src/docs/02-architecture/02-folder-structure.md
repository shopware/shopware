# Folder Structure (Skeleton)

- Top-level `src/Administration/Resources/app/administration/src` major folders:
  - `app/` – Vue app layer (entry points, bootstrap glue, router, initialization)
  - `core/` – Framework utilities (services, factories, helpers, mixins, state mgmt wrappers)
  - `meta/` – Test / metadata catalogs (datasets, identifiers, position indexes) (planned doc)
  - `module/` – Business domain modules (products, orders, customers, etc.)
    - Module internal typical structure:
      - `component/`, `page/`, `view/`, `acl/`, `snippet/`, `state/`, `service/`, `mixin/`
  - Rationale for separation `core` vs `module` vs `app`
- Where extension points live (inside Shopware plugins on a complete different place)