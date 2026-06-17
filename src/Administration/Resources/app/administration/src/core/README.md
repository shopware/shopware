# Core Layer

> **Detailed Docs**: `technical-docs/04-data-layer/` for Repository/Entity patterns
> **Coding Guidelines**: [Administration architecture](../../../../../../../coding-guidelines/administration/architecture.md)

## Key Directories

### `factory/` - Creation Patterns
- **component.factory**: Component registration (register, extend, override, wrapComponentConfig)
- **service.factory**: DI container (`Shopware.Service().register()`)
- **state.factory**: Pinia stores (`Shopware.Store.register()`)
- **module.factory**: Module registration
- **http.factory**: Axios with auth

### `data/` - Repository System
- **repository-factory**: Creates repositories
- **entity**: Reactive entities with `.getOrigin()`, `.getDraft()`
- **criteria**: Query builder from `@shopware-ag/meteor-admin-sdk`
- **changeset-generator**: Minimal diff (only changed fields)
- **error-resolver**: Maps API errors to fields

### `service/` - Core Services
- **api.service**: Base for domain services
- **login.service**: Auth, token refresh
- **validation.service**: Form validation
- **utils/**: Pure functions (format, string, object, types)

### `helper/` - Utilities
- **sanitizer**: XSS prevention
- **retry**: Exponential backoff
- **device**: Mobile/desktop detection
