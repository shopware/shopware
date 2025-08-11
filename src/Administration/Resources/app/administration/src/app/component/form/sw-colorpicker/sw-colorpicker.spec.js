/**
 * @sw-package framework
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
    it('should render the mt-colorpicker', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-colorpicker');
    });
});
