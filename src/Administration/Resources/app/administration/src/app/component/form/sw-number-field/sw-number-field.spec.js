/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-number-field', { sync: true }), {
        global: {
            stubs: {
                'mt-number-field': true,
                'sw-number-field-deprecated': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-number-field', () => {
    it('should render the mt-number-field', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-number-field');
    });
});
