/**
 * @sw-package framework
 */

const { TestEnvironment } = require('jest-environment-jsdom');

const pendingFeatureFlagsSymbol = Symbol.for('shopware.pendingActiveFeatureFlags');
const defaultActiveFeatureFlagsSymbol = Symbol.for('shopware.defaultActiveFeatureFlags');

class FeatureFlagTestEnvironment extends TestEnvironment {
    activeFeatureFlagsByTest = new WeakMap();

    handleTestEvent(event, state) {
        if (event.name === 'add_test') {
            // Read from the slot rather than the callback: it.each() hands its own wrapper to it(),
            // so a property on the callback would not reach us for table-driven tests.
            const featureFlags = this.global[pendingFeatureFlagsSymbol];
            const registeredTest = state.currentDescribeBlock.tests.at(-1);

            if (featureFlags && registeredTest) {
                this.activeFeatureFlagsByTest.set(registeredTest, featureFlags);
            }

            return;
        }

        if (event.name === 'test_start') {
            const featureFlags = this.activeFeatureFlagsByTest.get(event.test);

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

        if (!this.activeFeatureFlagsByTest.has(event.test)) {
            return;
        }

        this.global.activeFeatureFlags = [...(this.global[defaultActiveFeatureFlagsSymbol] ?? [])];
        delete this.global.activeFeatureFlagsForCurrentTest;
    }
}

module.exports = FeatureFlagTestEnvironment;
