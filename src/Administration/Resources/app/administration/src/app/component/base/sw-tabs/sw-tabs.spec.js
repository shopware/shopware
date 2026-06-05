/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-tabs', { sync: true }), {
        global: {
            stubs: {
                'sw-tabs-deprecated': true,
                'mt-tabs': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-tabs', () => {
    it('should warn once about the deprecated usage', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        const warnSpy = jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementation(() => {});

        await createWrapper();
        await createWrapper();

        expect(warnSpy).toHaveBeenCalledTimes(1);
        expect(warnSpy).toHaveBeenCalledWith(
            'sw-tabs',
            'The old usage of "sw-tabs" is deprecated and will be removed in v6.8.0.0. Please use "mt-tabs" with the "items" property instead.',
        );

        warnSpy.mockRestore();
    });

    it('should render the deprecated tabs when major feature flag is disabled', async () => {
        global.activeFeatureFlags = [''];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-tabs-deprecated');
        expect(wrapper.html()).not.toContain('mt-tabs');
    });

    it('should render the mt-tabs when major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-tabs');
    });
});
