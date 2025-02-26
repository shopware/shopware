/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-datepicker', { sync: true }), {
        global: {
            stubs: {
                'mt-datepicker': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-datepicker', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the mt-datepicker', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-datepicker');
    });
});
