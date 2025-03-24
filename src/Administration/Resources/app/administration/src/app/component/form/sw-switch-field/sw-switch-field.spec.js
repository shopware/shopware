/**
 * @sw-package framework
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

    it('should render the mt-switch', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-switch');
    });

    it('should use the correct checked value', async () => {
        global.activeFeatureFlags = ['ENABLE_METEOR_COMPONENTS'];

        const wrapper = await createWrapper();
        expect(wrapper.vm.checkedValue).toBe(false);

        await wrapper.setProps({ value: true });
        expect(wrapper.vm.checkedValue).toBe(true);

        await wrapper.setProps({ checked: true, value: null });
        expect(wrapper.vm.checkedValue).toBe(true);
    });
});
