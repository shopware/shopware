# Shopware 6

Shopware is an open-source e-commerce platform with API-first architecture exposing three distinct APIs (Admin, Store, Sync) alongside a built-in Twig-based storefront. It uses a custom Data Abstraction Layer instead of a traditional ORM, an event-driven extension system replacing decorators, and Flow Builder for business automation.

## Project Structure

```
shopware/
├── src/
│   ├── Core/                     # Business logic & framework
│   ├── Administration/           # Admin UI
│   ├── Storefront/               # Frontend
│   └── Elasticsearch/            # Search integration
├── tests/                        # Test suites
└── bin/console                   # CLI commands
```

## Technology Stack

- **Backend**: PHP 8.2+, Symfony 7, Doctrine DBAL 4
- **Frontend Admin**: Vue 3, Pinia + Vuex, Vite, TypeScript
- **Frontend Storefront**: Twig, Bootstrap 5, Webpack 5
- **Database**: MySQL 8+ / MariaDB 10.11+
- **Search**: OpenSearch 2 / Elasticsearch 8
- **Cache**: Redis (optional), Symfony Cache
- **Testing**: PHPUnit, PHPStan, Jest, Playwright

## Coding Guidelines

**MANDATORY**: All code must follow the guidelines in `coding-guidelines/`.

## File Linting

**MANDATORY**: All code must be linted according to the following table.

| File Type              | Check Command                 | Fix Command                                  |
|------------------------|-------------------------------|----------------------------------------------|
| **PHP** (.php)         | `composer ecs`                | `composer ecs-fix`                           |
| **PHP** (types)        | `composer phpstan`            | N/A - must fix manually                      |
| **JS/TS/Vue** (Admin)  | `composer eslint:admin`       | `composer eslint:admin:fix`                  |
| **JS/TS** (Storefront) | `composer eslint:storefront`  | `composer eslint:storefront:fix`             |
| **SCSS**               | `composer stylelint`          | `composer stylelint:[admin\|storefront]:fix` |
| **Twig** (Storefront)  | `composer ludtwig:storefront` | `composer ludtwig:storefront:fix`            |
| **Changelog**          | `composer lint:changelog`     | Manual fix required                          |
| **Snippets**           | `composer lint:snippets`      | Manual fix required                          |
| **Prettier** (Admin)   | `composer format:admin`       | `composer format:admin:fix`                  |
