/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-colorpicker', { sync: true }), {
        global: {
            stubs: {
                'sw-colorpicker-deprecated': true,
                'mt-colorpicker': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-colorpicker', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the deprecated colorpicker when major feature flag is disabled', async () => {
        global.activeFeatureFlags = [''];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-colorpicker-deprecated');
        expect(wrapper.html()).not.toContain('mt-colorpicker');
    });

    it('should render the mt-colorpicker when major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['v6.7.0.0'];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-colorpicker');
    });
});
