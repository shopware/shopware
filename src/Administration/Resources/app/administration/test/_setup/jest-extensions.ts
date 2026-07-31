/**
 * @sw-package framework
 */

type TestCallback = (() => unknown) | ((done: jest.DoneCallback) => unknown);

function getActiveFeatureFlags(): string[] {
    return globalThis.activeFeatureFlags ?? [];
}

function setActiveFeatureFlags(featureFlags: string[]): void {
    globalThis.activeFeatureFlags = featureFlags;
}

function runWithActiveFeatureFlags(featureFlags: readonly string[], callback: TestCallback): jest.ProvidesCallback {
    if (callback.length > 0) {
        return ((done: jest.DoneCallback) => {
            const previousFeatureFlags = getActiveFeatureFlags();
            setActiveFeatureFlags([
                ...new Set([
                    ...previousFeatureFlags,
                    ...featureFlags,
                ]),
            ]);

            const restoreFeatureFlags = () => setActiveFeatureFlags(previousFeatureFlags);
            const wrappedDone = ((...args: unknown[]) => {
                restoreFeatureFlags();

                done(...args);
            }) as jest.DoneCallback;

            wrappedDone.fail = (error?: string | { message: string }) => {
                restoreFeatureFlags();

                if (typeof done.fail === 'function') {
                    done.fail(error);

                    return;
                }

                done(error);
            };

            try {
                return (callback as (done: jest.DoneCallback) => unknown)(wrappedDone);
            } catch (error) {
                restoreFeatureFlags();
                throw error;
            }
        }) as jest.ProvidesCallback;
    }

    return () => {
        const previousFeatureFlags = getActiveFeatureFlags();
        setActiveFeatureFlags([
            ...new Set([
                ...previousFeatureFlags,
                ...featureFlags,
            ]),
        ]);

        try {
            return Promise.resolve((callback as () => unknown)()).finally(() => setActiveFeatureFlags(previousFeatureFlags));
        } catch (error) {
            setActiveFeatureFlags(previousFeatureFlags);
            throw error;
        }
    };
}

/** @private */
export function createDeprecatedTest(testFunction: jest.It): jest.It['deprecated'] {
    return (featureFlag: string) => {
        return (getActiveFeatureFlags().includes(featureFlag) ? testFunction.skip : testFunction) as jest.FeatureFlagTest;
    };
}

/** @private */
export function createActiveFeatureFlagsTest(testFunction: jest.It): jest.It['activeFeatureFlags'] {
    return (featureFlags: readonly string[]) => {
        return (name: string, callback?: TestCallback, timeout?: number) => {
            return testFunction(name, callback ? runWithActiveFeatureFlags(featureFlags, callback) : undefined, timeout);
        };
    };
}

it.deprecated = createDeprecatedTest(it);
it.activeFeatureFlags = createActiveFeatureFlagsTest(it);
