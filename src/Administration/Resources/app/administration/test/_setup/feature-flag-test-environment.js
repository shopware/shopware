/**
 * @sw-package framework
 *
 * Jest environment backing `it.activeFeatureFlags()`.
 *
 * Feature flags live in the mutable `global.activeFeatureFlags` array that the feature service mock
 * reads on every `isActive()` call. Activating them from inside a test callback is too late: setup
 * hooks (`beforeEach`) and the component mount they perform run first. So the flags have to be in
 * place before the test starts, which only the environment can do — it is the one place that sees
 * Jest's lifecycle events.
 *
 * The flow across three events:
 *
 * 1. `add_test`   — remember which flags a test was registered with (the helper leaves them in a
 *                   global slot for the duration of the synchronous `it()` call).
 * 2. `test_start` — merge them over the runner's baseline flags and publish, before any hook runs.
 * 3. `test_done`  — restore the baseline, so the next test is unaffected.
 *
 * `test/_setup/jest-extensions.ts` is the author-facing half; this file is the plumbing.
 */

const { TestEnvironment } = require('jest-environment-jsdom');

const pendingFeatureFlagsSymbol = Symbol.for('shopware.pendingActiveFeatureFlags');
const pendingInactiveFeatureFlagsSymbol = Symbol.for('shopware.pendingInactiveFeatureFlags');
const defaultActiveFeatureFlagsSymbol = Symbol.for('shopware.defaultActiveFeatureFlags');

class FeatureFlagTestEnvironment extends TestEnvironment {
    activeFeatureFlagsByTest = new WeakMap();
    inactiveFeatureFlagsByTest = new WeakMap();

    handleTestEvent(event, state) {
        if (event.name === 'add_test') {
            // Read from the slot rather than the callback: it.each() hands its own wrapper to it(),
            // so a property on the callback would not reach us for table-driven tests.
            const featureFlags = this.global[pendingFeatureFlagsSymbol];
            const registeredTest = state.currentDescribeBlock.tests.at(-1);

            if (featureFlags && registeredTest) {
                this.activeFeatureFlagsByTest.set(registeredTest, featureFlags);
            }

            const inactiveFeatureFlags = this.global[pendingInactiveFeatureFlagsSymbol];
            if (inactiveFeatureFlags && registeredTest) {
                this.inactiveFeatureFlagsByTest.set(registeredTest, inactiveFeatureFlags);
            }

            return;
        }

        if (event.name === 'test_start') {
            const featureFlags = this.activeFeatureFlagsByTest.get(event.test);
            const inactiveFeatureFlags = this.inactiveFeatureFlagsByTest.get(event.test) ?? [];

            if (!featureFlags && inactiveFeatureFlags.length === 0) {
                return;
            }

            const defaultActiveFeatureFlags = this.global[defaultActiveFeatureFlagsSymbol] ?? [];
            const activeFeatureFlags = [
                ...new Set([
                    ...defaultActiveFeatureFlags.filter((flag) => !inactiveFeatureFlags.includes(flag)),
                    ...(featureFlags ?? []),
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

            return;
        }

        if (
            ![
                'test_done',
                'test_skip',
                'test_todo',
            ].includes(event.name)
        ) {
            return;
        }

        // Past this point the event is one of test_done / test_skip / test_todo, i.e. the test is
        // finished. Only tests the helper registered flags for need restoring; everything else never
        // had them changed.
        if (!this.activeFeatureFlagsByTest.has(event.test) && !this.inactiveFeatureFlagsByTest.has(event.test)) {
            return;
        }

        this.global.activeFeatureFlags = [...(this.global[defaultActiveFeatureFlagsSymbol] ?? [])];
        delete this.global.activeFeatureFlagsForCurrentTest;
    }
}

module.exports = FeatureFlagTestEnvironment;
