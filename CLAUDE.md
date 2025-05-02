# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build/Lint/Test Commands

- Build: `composer setup` (full setup) or `composer build:js` (JS only)
- Run all tests: `composer phpunit`
- Run single test: `./vendor/bin/phpunit /path/to/TestFile.php`
- Run specific test method: `./vendor/bin/phpunit --filter methodName /path/to/TestFile.php`
- Static analysis: `composer phpstan`
- PHP CS Fixer: `composer ecs` (check) or `composer ecs-fix` (fix)
- JS linting: `composer eslint:admin` or `composer eslint:storefront`
- JS lint fix: `composer eslint:admin:fix` or `composer eslint:storefront:fix`
- JS unit tests: `composer admin:unit` or `composer storefront:unit`

## Coding Style Guidelines

- PHP: PSR-12 with `declare(strict_types=1)` at top of files
- Types: Use strict typing, declare return types, use PHPDoc for complex types
- JS/TS: Follow eslint rules, prefer TypeScript, use Vue 3 composition API
- Imports: Group by internal/external, alphabetical ordering
- File structure: Use Symfony bundle system for PHP, component structure for Vue
- Naming: PascalCase for classes, camelCase for methods/variables, kebab-case for Vue components
- Error handling: Use domain-specific exceptions, don't silence errors
- Vue components: Follow file structure standards and use proper lifecycle hooks
- For more code style guidelines refer to the documentation in the `/coding-guidlines` folder

## Architectural consideration
* ADRs are stored in `/adr` folder, use it to get an understanding of past architectural decisions and confirm to them when possible

## Documentation

* When referencing code, use format: `file_path:line_number`
* Official documentation is available in https://github.com/shopware/docs