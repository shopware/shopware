/**
 * @sw-package framework
 */

const { TestEnvironment } = require('jest-environment-jsdom');

const activeFeatureFlagsSymbol = Symbol.for('shopware.activeFeatureFlags');

class FeatureFlagTestEnvironment extends TestEnvironment {
    activeFeatureFlagsByTest = new WeakMap();

    handleTestEvent(event, state) {
        if (event.name === 'add_test') {
            const featureFlags = event.fn[activeFeatureFlagsSymbol];
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

            const activeFeatureFlags = [...new Set(featureFlags)];

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

        this.global.activeFeatureFlags = [];
        delete this.global.activeFeatureFlagsForCurrentTest;
    }
}

module.exports = FeatureFlagTestEnvironment;
