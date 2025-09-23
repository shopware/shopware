# Context & Authentication (Skeleton)

- Context types: API context (language, currency, version), User session context
- Authentication flow:
  - Login request obtains token
  - Token stored (in cookie storage)
  - Attached to subsequent API service requests
  - Refresh / expiry handling