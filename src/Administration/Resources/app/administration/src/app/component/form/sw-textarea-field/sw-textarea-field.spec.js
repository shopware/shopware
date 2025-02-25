/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-textarea-field', { sync: true }), {
        global: {
            stubs: {
                'mt-textarea': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-textarea-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the mt-textarea-field when major feature flag is enabled', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-textarea');
    });
});
