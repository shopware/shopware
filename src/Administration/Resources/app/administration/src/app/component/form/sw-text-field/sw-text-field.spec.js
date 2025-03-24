/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-text-field', { sync: true }), {
        global: {
            stubs: {
                'sw-text-field-deprecated': true,
                'mt-text-field': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-text-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the mt-text-field', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-text-field');
    });
});
