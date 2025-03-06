/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-checkbox-field', { sync: true }), {
        global: {
            stubs: {
                'mt-checkbox': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-checkbox-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the mt-checkbox', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-checkbox');
    });
});
