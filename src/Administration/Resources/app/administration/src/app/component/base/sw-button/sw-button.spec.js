/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

describe('components/base/sw-button', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = mount(await wrapTestComponent('sw-button', { sync: true }));
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the deprecated button when major feature flag is disabled', async () => {
        global.activeFeatureFlags = [''];

        const wrapper = mount(await wrapTestComponent('sw-button', { sync: true }));

        expect(wrapper.html()).toContain('sw-button-deprecated');
        expect(wrapper.html()).not.toContain('mt-button');
    });

    it('should render the mt-button when major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['v6.7.0.0'];

        const wrapper = mount(await wrapTestComponent('sw-button', { sync: true }));

        expect(wrapper.html()).toContain('mt-button');
    });
});
