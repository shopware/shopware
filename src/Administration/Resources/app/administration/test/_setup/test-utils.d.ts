/**
 * @sw-package framework
 * @private
 */
// eslint-disable-next-line @typescript-eslint/no-unused-vars
import { VueWrapper } from '@vue/test-utils';

declare global {
    var activeFeatureFlags: string[];

    namespace jest {
        interface FeatureFlagTest {
            (name: string, fn?: (() => unknown) | ((done: DoneCallback) => unknown), timeout?: number): void;
        }

        interface It {
            /** Skip this test when the given major feature flag is active. */
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
