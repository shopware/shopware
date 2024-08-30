/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-button', { sync: true }), {
        global: {
            stubs: {
                'mt-button': true,
                'sw-button-deprecated': true,
            },
        },
    });
}

describe('components/base/sw-button', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the deprecated button when major feature flag is disabled', async () => {
        global.activeFeatureFlags = [''];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-button-deprecated');
        expect(wrapper.html()).not.toContain('mt-button');
    });

    it('should render the mt-button when major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['v6.7.0.0'];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-button');
    });
});
