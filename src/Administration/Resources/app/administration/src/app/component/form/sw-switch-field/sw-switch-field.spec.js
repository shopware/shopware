/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-switch-field', { sync: true }), {
        global: {
            stubs: {
                'sw-switch-field-deprecated': true,
                'mt-switch': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-switch-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the deprecated switch-field when major feature flag is disabled', async () => {
        global.activeFeatureFlags = [''];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-switch-field-deprecated');
        expect(wrapper.html()).not.toContain('mt-switch');
    });

    it('should render the mt-switch when major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['v6.7.0.0'];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-switch');
    });
});
