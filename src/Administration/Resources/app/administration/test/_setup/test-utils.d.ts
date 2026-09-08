/**
 * @sw-package framework
 * @private
 */
// eslint-disable-next-line @typescript-eslint/no-unused-vars
import { VueWrapper } from '@vue/test-utils';

declare global {
    var activeFeatureFlags: string[];

    /**
     * Console output a spec expects, so `prepare_environment` silences it instead of failing.
     * A `msg` string matches a substring of the first argument, a RegExp is tested against it, and
     * `msgCheck` receives the first two arguments for anything those two cannot express.
     */
    var allowedErrors: {
        method: 'error' | 'warn';
        msg?: string | RegExp;
        msgCheck?: (...args: unknown[]) => boolean;
    }[];

    namespace jest {
        interface FeatureFlagTest {
            (name: string, fn?: (() => unknown) | ((done: DoneCallback) => unknown), timeout?: number): void;

            /** Table-driven variant, same semantics as `it.each`. */
            each: It['each'];
        }

        interface It {
            /**
             * Expect this test to fail when the given major feature flag is active — the behaviour
             * it asserts is gone by then, so a test that still passes means `removedIn` names the
             * wrong version. The registered test name gains a ` (removed in <removedIn>)` suffix,
             * so legacy/v6.8 pairs do not share a title.
             */
            deprecated(removedIn: string): FeatureFlagTest;

            /** Activate the given feature flags for the duration of this test. */
            activeFeatureFlags(featureFlags: readonly string[]): FeatureFlagTest;
        }
    }
}

declare module '@vue/test-utils' {
    interface VueWrapper<T> {
        findByText(selector: string, text: string): VueWrapper<T> | null;
        findByAriaLabel(selector: string, text: string): VueWrapper<T> | null;
        findByLabel(text: string): VueWrapper<T> | null;
        findByPlaceholder(text: string): VueWrapper<T> | null;
    }
}
