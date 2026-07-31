/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-loader', { sync: true }), {
        global: {
            stubs: {
                'mt-loader': true,
                'sw-loader-deprecated': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-loader', () => {
    // CHANGE REASON: This assertion covers the legacy sw-loader implementation scheduled for removal in v6.8.0.0. @removed @migrated
    // @deprecated tag:v6.8.0.0 - The test will be removed with the legacy sw-loader implementation.
    it.deprecated('v6.8.0.0')('should render the deprecated sw-loader when major feature flag is disabled', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-loader-deprecated');
        expect(wrapper.html()).not.toContain('mt-loader');
    });

    // CHANGE REASON: Scope ENABLE_METEOR_COMPONENTS declaratively to the Meteor loader test. @migrated
    it.activeFeatureFlags(['ENABLE_METEOR_COMPONENTS'])(
        'should render the mt-loader when major feature flag is enabled',
        async () => {
            const wrapper = await createWrapper();

            expect(wrapper.html()).toContain('mt-loader');
        },
    );
});
