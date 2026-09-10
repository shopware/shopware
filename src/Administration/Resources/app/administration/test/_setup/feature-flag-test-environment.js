/**
 * @sw-package framework
 *
 * Jest environment backing `it.activeFeatureFlags()` and `it.deprecated()`.
 *
 * Feature flags live in the mutable `global.activeFeatureFlags` array that the feature service mock
 * reads on every `isActive()` call. Activating them from inside a test callback is too late: setup
 * hooks (`beforeEach`) and the component mount they perform run first. So the flags have to be in
 * place before the test starts, which only the environment can do — it is the one place that sees
 * Jest's lifecycle events.
 *
 * The flow across three events:
 *
 * 1. `add_test`   — remember what a test was registered with (the helper leaves it in a global slot
 *                   for the duration of the synchronous `it()` call).
 * 2. `test_start` — publish it before any hook runs: the flags merged over the runner's baseline,
 *                   and whether the test is expected to fail.
 * 3. `test_done`  — restore the baseline, so the next test is unaffected.
 *
 * `test/_setup/jest-extensions.ts` is the author-facing half; this file is the plumbing.
 */

const { TestEnvironment } = require('jest-environment-jsdom');

const pendingFeatureFlagsSymbol = Symbol.for('shopware.pendingActiveFeatureFlags');
const defaultActiveFeatureFlagsSymbol = Symbol.for('shopware.defaultActiveFeatureFlags');
const pendingExpectedFailureSymbol = Symbol.for('shopware.pendingTestExpectsFailure');
const expectedFailureSymbol = Symbol.for('shopware.currentTestExpectsFailure');

const testFinishedEvents = [
    'test_done',
    'test_skip',
    'test_todo',
];

class FeatureFlagTestEnvironment extends TestEnvironment {
    activeFeatureFlagsByTest = new WeakMap();

    expectedFailureTests = new WeakSet();

    handleTestEvent(event, state) {
        if (event.name === 'add_test') {
            this.rememberTestRegistration(state);

            return;
        }

        if (event.name === 'test_start') {
            this.publishForTest(event.test);

            return;
        }

        if (testFinishedEvents.includes(event.name)) {
            this.restoreAfterTest(event.test);
        }
    }

    /**
     * Reads what the helper left in its global slots and attaches it to the test Jest just added.
     *
     * Reading the slots rather than the callback: `it.each()` hands its own wrapper to `it()`, so a
     * property on the callback would not reach us for table-driven tests.
     */
    rememberTestRegistration(state) {
        const registeredTest = state.currentDescribeBlock.tests.at(-1);

        if (!registeredTest) {
            return;
        }

        const featureFlags = this.global[pendingFeatureFlagsSymbol];

        if (featureFlags) {
            this.activeFeatureFlagsByTest.set(registeredTest, featureFlags);
        }

        if (this.global[pendingExpectedFailureSymbol]) {
            this.expectedFailureTests.add(registeredTest);
        }
    }

    /** Publishes a test's flags and its expected-failure marker, before its first hook runs. */
    publishForTest(test) {
        if (this.expectedFailureTests.has(test)) {
            this.global[expectedFailureSymbol] = true;
        }

        const featureFlags = this.activeFeatureFlagsByTest.get(test);

        if (!featureFlags) {
            return;
        }

        const defaultActiveFeatureFlags = this.global[defaultActiveFeatureFlagsSymbol] ?? [];
        const activeFeatureFlags = [
            ...new Set([
                ...defaultActiveFeatureFlags,
                ...featureFlags,
            ]),
        ];

        // `activeFeatureFlags` is what the feature service mock reads. The extra
        // `activeFeatureFlagsForCurrentTest` marker exists because prepare_environment.js resets
        // `activeFeatureFlags` to the baseline in its own global `beforeEach`, which runs *after*
        // this event — without the marker that reset would immediately undo the line below. It
        // doubles as the "a helper is driving this test" signal for the feature mock shadowing
        // check in the same file.
        this.global.activeFeatureFlagsForCurrentTest = activeFeatureFlags;
        this.global.activeFeatureFlags = activeFeatureFlags;
    }

    /** Undoes `publishForTest`. Only tests a helper drove were changed; everything else is left alone. */
    restoreAfterTest(test) {
        delete this.global[expectedFailureSymbol];

        if (!this.activeFeatureFlagsByTest.has(test)) {
            return;
        }

        this.global.activeFeatureFlags = [...(this.global[defaultActiveFeatureFlagsSymbol] ?? [])];
        delete this.global.activeFeatureFlagsForCurrentTest;
    }
}

module.exports = FeatureFlagTestEnvironment;
