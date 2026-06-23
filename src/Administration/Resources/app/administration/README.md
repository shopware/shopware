# Administration

> Shopware Administration

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
