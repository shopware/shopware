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
    // @deprecated tag:v6.8.0 - The test will be removed with the legacy tabs implementation.
    it.deprecated('v6.8.0.0')('should render the deprecated tabs', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-tabs-deprecated');
        expect(wrapper.html()).not.toContain('mt-tabs');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render the mt-tabs', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-tabs');
    });
});
