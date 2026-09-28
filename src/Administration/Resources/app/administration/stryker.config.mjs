/**
 * @sw-package framework
 *
 * StrykerJS mutation testing pilot for the Administration.
 *
 * Scope is deliberately limited to the pure-logic layer under `src/core` (data layer, helpers,
 * factories, services). Component `index.js` files and their Twig templates are out of scope:
 * Stryker cannot mutate templates, and shallow mount specs would only produce noise.
 *
 * Run (needs `npm run unit-setup` once, like the Jest suite):
 *   npm run mutation:changed                              # only files changed against trunk (recommended)
 *   npm run mutation                                      # full src/core pilot, incremental, takes hours
 *   npm run mutation -- --mutate src/core/helper/retry.helper.js   # single file
 *
 * Notes:
 * - `inPlace` is required: the Jest config resolves `roots` into the Storefront tree and reads
 *   generated files relative to this directory, which breaks in Stryker's copied sandbox.
 *   Stryker restores the mutated files on exit; do not run it while editing in the same tree.
 * - `coverageAnalysis: 'perTest'` is what keeps the run time in check. Stryker wraps the custom
 *   feature-flag test environment itself, no extra mixin file is needed, but the environment path
 *   has to be absolute (see `jest.config` below).
 * - `patches/@stryker-mutator+instrumenter+*.patch` keeps the project `.babelrc` (Babel 7 presets) out of
 *   Stryker's Babel 8 based JS parser; the package.json `overrides` entry gives the instrumenter its own
 *   `@babel/core` 8 for the same reason.
 */

import { fileURLToPath } from 'node:url';

/** @type {import('@stryker-mutator/api/core').PartialStrykerOptions} */
const config = {
    testRunner: 'jest',
    jest: {
        configFile: 'jest.config.js',
        enableFindRelatedTests: true,
        config: {
            // Stryker wraps the configured environment itself and resolves it with a plain
            // `require.resolve`, which does not expand Jest's `<rootDir>` placeholder.
            testEnvironment: fileURLToPath(new URL('./test/_setup/feature-flag-test-environment.js', import.meta.url)),
            testPathIgnorePatterns: [
                '/node_modules/',
                // Stryker runs every related spec in-band in one process. This spec's debounced
                // search watcher then fires an extra `getList()` and its call-count assertions fail,
                // while the same spec is green in the regular worker-based Jest run.
                '<rootDir>/src/module/sw-product/page/sw-product-list/sw-product-list.spec.js',
            ],
        },
    },
    coverageAnalysis: 'perTest',
    inPlace: true,
    incremental: true,
    incrementalFile: 'build/artifacts/stryker/incremental.json',
    tempDirName: '.stryker-tmp',

    mutate: [
        'src/core/**/*.{js,ts}',
        '!src/core/**/*.spec.{js,ts}',
        '!src/core/**/*.spec/**',
        // `import.meta.glob` loaders: Jest resolves the glob statically via the `shopware-vite-meta-glob`
        // Babel plugin, which needs the literal string. Instrumented, the glob is no longer static.
        '!src/core/service/api/index.ts',
    ],

    // No TypeScript checker is configured, so `// @ts-nocheck` headers are pointless. The default
    // pattern also touches unrelated files such as `index.html`.
    disableTypeChecks: false,

    // Static mutants (module-level code) need the full related suite per mutant. Skip them in the
    // pilot; the score then only reflects mutants inside functions, which is what we want to learn about.
    ignoreStatic: true,

    concurrency: 4,
    timeoutMS: 10000,
    timeoutFactor: 2,

    // Stryker runs Jest in-band inside one long-lived process per concurrency slot, so the worker
    // recycling that `workerIdleMemoryLimit` gives the regular suite does not apply. The initial
    // run of every related spec exceeds Node's default heap.
    testRunnerNodeArgs: ['--max-old-space-size=12288'],

    reporters: [
        'html',
        'clear-text',
        'progress',
    ],
    htmlReporter: {
        fileName: 'build/artifacts/stryker/mutation.html',
    },
    clearTextReporter: {
        allowColor: true,
        logTests: false,
        maxTestsToLog: 3,
    },
    thresholds: {
        high: 80,
        low: 60,
        // No break threshold during the pilot: the run reports, it does not gate.
        break: null,
    },
};

export default config;
