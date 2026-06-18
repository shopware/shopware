# Administration

> Shopware Administration

## Working Here

See `technical-docs/` for detailed architecture guides. More specific working guidance lives in the README files under `src/core/`, `src/app/`, and `src/module/`.

### File Structure

```
technical-docs/     # Full technical documentation
src/
├── core/           # Vue-independent framework code, repositories, services
├── app/            # Vue-specific UI, components, stores
└── module/         # Business modules
```

### Technologies

- TypeScript for new code
- Vue 3 components compiled at runtime
- Twig.JS for extensible Vue component templates
- Pinia and legacy Vuex for state
- Vue Router, Axios, Vite, Jest

### Shopware-Specific Patterns

- The Administration uses a component factory instead of regular local Vue component imports so plugins can extend and override components.
- The boot sequence initializes state, config, feature flags, core modules, services, and plugins before mounting Vue.
- The global `Shopware` object is the central access point for services, factories, and dependency injection.

### Coding Guidelines

- [Administration architecture](../../../../../coding-guidelines/administration/architecture.md)
- [Administration testing](../../../../../coding-guidelines/administration/testing.md)
- [Administration feature flags and deprecations](../../../../../coding-guidelines/administration/feature-flags-and-deprecations.md)

Admin UI changes that read or persist DAL entities or associations must update matching ACL privilege mappings and migrations for existing roles when needed.

### Related ADRs

- [Co-locate Administration Technical Documentation with Source Code](../../../../../adr/2025-10-14-colocate-administration-technical-docs.md)
- [Administration Pull Request Guidelines](../../../../../adr/2026-05-13-administration-pr-guidelines.md)

### Composer Wrappers

```bash
composer eslint:admin
composer eslint:admin:fix
composer stylelint:admin
composer stylelint:admin:fix
composer format:admin
composer format:admin:fix
composer admin:unit
composer admin:unit:watch
composer build:js:admin
```

## Build Setup

``` bash
# install dependencies
npm install

# serve with hot reload at localhost:8080
npm run dev

# build for production with minification
npm run build

# build for production and view the bundle analyzer report
npm run build --report

# run unit tests
npm run unit

# run e2e tests
npm run e2e

# run all tests
npm test
```

For detailed explanation on how things work, checkout the [guide](http://vuejs-templates.github.io/webpack/) and [docs for vue-loader](http://vuejs.github.io/vue-loader).

## Performance Testing

Performance tests use Lighthouse CI to measure real-world runtime performance of the Administration panel, focusing on boot process and interactivity metrics.

### Prerequisites

1. Ensure Shopware is installed and running locally (e.g., via `symfony server:start`)
2. The admin panel should be accessible at `http://localhost:8000/admin`
3. Default admin credentials should work (admin/shopware)

### Running Performance Tests

```bash
# Run full performance test (collect + assert)
# This will collect metrics and check against thresholds
npm run perf

# Collect performance metrics only (no assertions)
# Useful for quick checks without failing on thresholds
npm run perf:collect

# Assert against previously collected metrics
# Run after perf:collect to check thresholds
npm run perf:assert
```

The performance tests measure:
- **Time to Interactive (TTI)**: When the page becomes fully interactive
- **Total Blocking Time (TBT)**: JavaScript processing load during boot
- **First Contentful Paint (FCP)**: Initial visual render
- **Largest Contentful Paint (LCP)**: Main UI visibility
- **Performance Score**: Overall composite score

Configuration is in `lighthouserc.js`. Reports are saved to `.lighthouseci/` directory.


## Twig Linting Setup

### VSCode

Should work out of the box @see [.vscode/settings.json](../../../../../.vscode/settings.json).

### PHPStorm

Add `html,twig` to `eslint.additional.file.extensions` list in Registry (Help > Find Action..., type registry... to locate it) and re-start the IDE.
