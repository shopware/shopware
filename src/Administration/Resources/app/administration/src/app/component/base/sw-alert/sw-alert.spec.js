/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-alert', { sync: true }), {
        global: {
            stubs: {
                'sw-alert-deprecated': await wrapTestComponent('sw-alert-deprecated'),
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-alert', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the deprecated alert when major feature flag is disabled', async () => {
        global.activeFeatureFlags = [''];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-alert-deprecated');
        expect(wrapper.html()).not.toContain('mt-banner');
    });

    it('should render the mt-banner when major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['v6.7.0.0'];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-banner');
    });
});
