/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-number-field', { sync: true }), {
        global: {
            stubs: {
                'mt-number-field': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-number-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the mt-number-field when major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['ENABLE_METEOR_COMPONENTS'];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-number-field');
    });
});
