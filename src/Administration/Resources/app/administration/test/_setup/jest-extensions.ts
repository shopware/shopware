/**
 * @sw-package framework
 */

type TestCallback = (() => unknown) | ((done: jest.DoneCallback) => unknown);
const activeFeatureFlagsSymbol = Symbol.for('shopware.activeFeatureFlags');

function getActiveFeatureFlags(): string[] {
    return globalThis.activeFeatureFlags ?? [];
}

/** @private */
export function createDeprecatedTest(testFunction: jest.It): jest.It['deprecated'] {
    return (removedIn: string) => {
        return (getActiveFeatureFlags().includes(removedIn) ? testFunction.skip : testFunction) as jest.FeatureFlagTest;
    };
}

/** @private */
export function createActiveFeatureFlagsTest(testFunction: jest.It): jest.It['activeFeatureFlags'] {
    return (featureFlags: readonly string[]) => {
        return (name: string, callback?: TestCallback, timeout?: number) => {
            if (!callback) {
                testFunction(name, undefined, timeout);

                return;
            }

            Object.defineProperty(callback, activeFeatureFlagsSymbol, {
                configurable: true,
                value: [...featureFlags],
            });

            try {
                testFunction(name, callback as jest.ProvidesCallback, timeout);
            } finally {
                Reflect.deleteProperty(callback, activeFeatureFlagsSymbol);
            }
        };
    };
}

it.deprecated = createDeprecatedTest(it);
it.activeFeatureFlags = createActiveFeatureFlagsTest(it);
