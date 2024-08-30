/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('sw-label', { sync: true }), {
        global: {
            stubs: {
                'sw-icon': true,
                'sw-color-badge': true,
            },
        },
        props: propsData,
    });
}

describe('src/app/component/base/sw-label', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be dismissable', async () => {
        const wrapper = await createWrapper({ dismissable: true });

        expect(wrapper.find('sw-label__dismiss')).toBeTruthy();
    });

    it('should not be dismissable', async () => {
        const wrapper = await createWrapper({ dismissable: false });

        expect(wrapper.find('sw-label__dismiss').exists()).toBeFalsy();
    });
});
