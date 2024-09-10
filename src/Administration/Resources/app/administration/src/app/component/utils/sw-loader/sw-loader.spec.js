/**
 * @package admin
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
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the deprecated sw-loader when major feature flag is disabled', async () => {
        global.activeFeatureFlags = [''];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-loader-deprecated');
        expect(wrapper.html()).not.toContain('mt-loader');
    });

    it('should render the mt-loader when major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['v6.7.0.0'];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-loader');
    });
});
