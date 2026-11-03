/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-select-field', { sync: true }), {
        props: {
            options: [],
        },
        global: {
            stubs: {
                'sw-select-field-deprecated': true,
            },
        },
    });
}

describe('src/app/component/base/sw-select-field', () => {
    it('should render the mt-select-field', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-select');
    });
});
