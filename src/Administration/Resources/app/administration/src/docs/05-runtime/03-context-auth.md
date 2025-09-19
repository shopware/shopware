# Context & Authentication (Skeleton)

- Context types: API context (language, currency, version), User session context
- Authentication flow:
  - Login request obtains token
  - Token stored (in memory / storage) (confirm exact mechanism)
  - Attached to subsequent API service requests
  - Refresh / expiry handling (placeholder)
- ACL permission resolution:
  - Server authoritative, client for conditional UI hiding
  - Privilege keys mapping to UI actions
- Multi-language / locale selection interaction with context
- Impersonation / admin switching (if supported) placeholder
- Security notes:
  - Never expose raw credentials to plugins/apps
  - Token scoping rules
- Future enhancements: short-lived tokens + refresh rotation
