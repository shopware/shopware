/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

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
    // CHANGE REASON: This assertion covers the legacy sw-skeleton-bar implementation scheduled for removal in v6.8.0.0. @removed @migrated
    // @deprecated tag:v6.8.0.0 - The test will be removed with the legacy sw-skeleton-bar implementation.
    it.deprecated('v6.8.0.0')('should render the deprecated skeleton-bar when major feature flag is disabled', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-skeleton-bar-deprecated');
        expect(wrapper.html()).not.toContain('mt-skeleton-bar');
    });

    // CHANGE REASON: Scope ENABLE_METEOR_COMPONENTS declaratively to the Meteor skeleton test. @migrated
    it.activeFeatureFlags(['ENABLE_METEOR_COMPONENTS'])(
        'should render the mt-skeleton-bar when major feature flag is enabled',
        async () => {
            const wrapper = await createWrapper();

            expect(wrapper.html()).toContain('mt-skeleton-bar');
        },
    );
});
