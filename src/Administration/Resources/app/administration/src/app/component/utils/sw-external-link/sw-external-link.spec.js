/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-external-link', { sync: true }), {
        global: {
            stubs: {
                'sw-external-link-deprecated': true,
                'mt-external-link': true,
            },
        },
        props: {},
    });
}

describe('src/app/component/base/sw-external-link', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the deprecated external-link when major feature flag is disabled', async () => {
        global.activeFeatureFlags = [''];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-external-link-deprecated');
        expect(wrapper.html()).not.toContain('mt-external-link');
    });

    it('should render the mt-external-link when major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['v6.7.0.0'];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-external-link');
    });
});
