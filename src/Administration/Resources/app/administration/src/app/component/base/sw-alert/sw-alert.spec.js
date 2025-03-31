/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-alert', { sync: true }), {
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-alert', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the mt-banner', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-banner');
    });
});
