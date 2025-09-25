# API Services (Skeleton)

- Purpose: Encapsulate non-generic endpoints (bulk ops, custom logic)
- Location pattern: `core/service/api/*.api.service.js`
- Registration & usage via `Shopware.Service('name')`
- Typical concerns handled:
  - Authentication headers
  - HTTP Client (using Axios)
- Example categories: theme API, import/export, cache, search
- Decorators for extension (service decoration pattern)