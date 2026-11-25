/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { allowDeprecation, deprecations } from 'test/_helper_/deprecations';

const oldUsageDeprecation = 'The old usage of "sw-skeleton-bar" is deprecated';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-skeleton-bar', { sync: true }), {
        global: {
            stubs: {
                'sw-skeleton-bar-deprecated': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-skeleton-bar', () => {
    beforeEach(() => {
        allowDeprecation(oldUsageDeprecation);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-skeleton-bar implementation.
    it.deprecated('v6.8.0.0')('should render the deprecated skeleton-bar and report it as deprecated', async () => {
        const wrapper = await createWrapper();

        expect(deprecations).toHaveTriggered(oldUsageDeprecation);
        expect(wrapper.html()).toContain('sw-skeleton-bar-deprecated');
        expect(wrapper.html()).not.toContain('mt-skeleton-bar');
    });

    it.activeFeatureFlags(['ENABLE_METEOR_COMPONENTS'])('should render the mt-skeleton-bar', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-skeleton-bar');
        expect(deprecations).not.toHaveTriggered(oldUsageDeprecation);
    });
});
