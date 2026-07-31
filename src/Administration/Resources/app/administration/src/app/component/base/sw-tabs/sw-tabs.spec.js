/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-tabs', { sync: true }), {
        global: {
            stubs: {
                'sw-tabs-deprecated': true,
                'mt-tabs': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-tabs', () => {
    // CHANGE REASON: The legacy tabs implementation is removed when V6_8_0_0 becomes the baseline. @removed @migrated
    // @deprecated tag:v6.8.0.0 - The test will be removed with the legacy tabs implementation.
    it.deprecated('v6.8.0.0')('should render the deprecated tabs when major feature flag is disabled', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-tabs-deprecated');
        expect(wrapper.html()).not.toContain('mt-tabs');
    });

    // CHANGE REASON: Scope V6_8_0_0 declaratively to the Meteor tabs test. @migrated
    it.activeFeatureFlags(['V6_8_0_0'])('should render the mt-tabs when major feature flag is enabled', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-tabs');
    });
});
