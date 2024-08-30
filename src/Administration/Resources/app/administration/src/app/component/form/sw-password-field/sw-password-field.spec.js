/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-password-field', { sync: true }), {
        global: {
            stubs: {
                'sw-password-field-deprecated': true,
                'mt-password-field': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-password-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the deprecated password-field when major feature flag is disabled', async () => {
        global.activeFeatureFlags = [''];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-password-field-deprecated');
        expect(wrapper.html()).not.toContain('mt-password-field');
    });

    it('should render the mt-password-field when major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['v6.7.0.0'];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-password-field');
    });
});
