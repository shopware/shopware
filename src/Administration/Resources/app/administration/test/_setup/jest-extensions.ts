/**
 * @sw-package framework
 */

import normalizeFeatureFlag from '../_helper_/normalizeFeatureFlag';

type EachTable = Parameters<jest.It['each']>[0];
type TestCallback = (() => unknown) | ((done: jest.DoneCallback) => unknown);
// jest's `.each(table)(...)` types its callback for the table row args; our helpers forward a plain
// ProvidesCallback, so the returned registrar is cast to accept it. Runtime behaviour is unchanged.
type EachRegister = (name: string, callback: jest.ProvidesCallback, timeout?: number) => void;
// `.each` supports both an inline table and a tagged-template call — `each`a|b`(...)` — which JS
// invokes as (strings, ...values). Forward every argument so interpolated values are not dropped.
type EachArgs = [table: EachTable] | [strings: TemplateStringsArray, ...values: unknown[]];
const pendingFeatureFlagsSymbol = Symbol.for('shopware.pendingActiveFeatureFlags');
const pendingExpectedFailureSymbol = Symbol.for('shopware.pendingTestExpectsFailure');

function getActiveFeatureFlags(): string[] {
    return globalThis.activeFeatureFlags ?? [];
}

/**
 * Publishes the flags for the tests registered by `register`.
 *
 * Jest fires `add_test` synchronously from inside `it()`, so the environment can read the flags off
 * this slot while the registration is in flight. Tagging the callback instead would not survive
 * `it.each`, which hands its own wrapper to `it()` rather than the callback it was given.
 */
function withPendingFeatureFlags<T>(featureFlags: readonly string[], register: () => T): T {
    Reflect.set(globalThis, pendingFeatureFlagsSymbol, featureFlags.map(normalizeFeatureFlag));

    try {
        return register();
    } finally {
        Reflect.deleteProperty(globalThis, pendingFeatureFlagsSymbol);
    }
}

/**
 * Marks the tests registered by `register` as expected to fail.
 *
 * The environment picks this up on `add_test` and republishes it for the running test, which is what
 * lets `prepare_environment.js` keep its console guard quiet for a test whose failure is the point.
 * Same slot mechanism as `withPendingFeatureFlags`, for the same reason: `it.each` hands its own
 * wrapper to `it()`, so a property on the callback would not survive a table.
 */
function withPendingExpectedFailure<T>(register: () => T): T {
    Reflect.set(globalThis, pendingExpectedFailureSymbol, true);

    try {
        return register();
    } finally {
        Reflect.deleteProperty(globalThis, pendingExpectedFailureSymbol);
    }
}

/** @private */
export function createDeprecatedTest(testFunction: jest.It): jest.It['deprecated'] {
    return (removedIn: string) => {
        const normalizedRemovedIn = normalizeFeatureFlag(removedIn);
        const isRemoved = getActiveFeatureFlags().some((featureFlag) => {
            return normalizeFeatureFlag(featureFlag) === normalizedRemovedIn;
        });
        // Inverted instead of skipped: with the removal flag on, the behaviour the test asserts is
        // gone, so the test still passing means `removedIn` names the wrong version. Jest only
        // inverts what the callback itself throws, so a throw from a spec's own `beforeEach` still
        // reports as a plain failure; the console guard is covered by the marker below.
        const register = isRemoved ? testFunction.failing : testFunction;
        const publish = isRemoved ? withPendingExpectedFailure : <T>(registerTest: () => T) => registerTest();

        // Applied before Jest interpolates `%s` and friends, so the suffix trails the whole title.
        const withSuffix = (name: string) => `${name} (removed in ${removedIn})`;

        const run = ((name: string, callback?: TestCallback, timeout?: number) => {
            publish(() => register(withSuffix(name), callback as jest.ProvidesCallback, timeout));
        }) as jest.FeatureFlagTest;

        run.each = ((...eachArgs: EachArgs) =>
            (name: string, callback: jest.ProvidesCallback, timeout?: number) =>
                publish(() =>
                    (register.each as (...args: EachArgs) => EachRegister)(...eachArgs)(withSuffix(name), callback, timeout),
                )) as jest.It['each'];

        return run;
    };
}

/** @private */
export function createActiveFeatureFlagsTest(testFunction: jest.It): jest.It['activeFeatureFlags'] {
    return (featureFlags: readonly string[]) => {
        const run = ((name: string, callback?: TestCallback, timeout?: number) => {
            withPendingFeatureFlags(featureFlags, () => {
                testFunction(name, callback as jest.ProvidesCallback, timeout);
            });
        }) as jest.FeatureFlagTest;

        // `it.each(table)(...)` registers every row synchronously inside this one call, so all rows
        // pick up the same flags.
        run.each = ((...eachArgs: EachArgs) =>
            (name: string, callback: jest.ProvidesCallback, timeout?: number) =>
                withPendingFeatureFlags(featureFlags, () =>
                    (testFunction.each as (...args: EachArgs) => EachRegister)(...eachArgs)(name, callback, timeout),
                )) as jest.It['each'];

        return run;
    };
}

it.deprecated = createDeprecatedTest(it);
it.activeFeatureFlags = createActiveFeatureFlagsTest(it);
