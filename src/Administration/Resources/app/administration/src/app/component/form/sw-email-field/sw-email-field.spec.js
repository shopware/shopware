/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-email-field', { sync: true }), {
        global: {
            stubs: {
                'mt-email-field': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-email-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the mt-email-field', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-email-field');
    });
});
