/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-popover', { sync: true }), {
        global: {
            stubs: {
                'sw-popover-deprecated': true,
                'mt-floating-ui': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-popover', () => {
    it('should render the deprecated popover when major feature flag is disabled', async () => {
        global.activeFeatureFlags = [''];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-popover-deprecated');
        expect(wrapper.html()).not.toContain('mt-floating-ui');
    });

    it('should render the mt-floating-ui when major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-floating-ui');
    });

    it('should pass the "resizeWidth" prop to the "matchReferenceWidth" property in mt-floating-ui with true', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const warnSpy = jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementation();

        const wrapper = await createWrapper({
            props: {
                resizeWidth: true,
            },
        });

        const floatingUi = wrapper.findComponent({ name: 'mt-floating-ui' });
        expect(floatingUi.attributes('match-reference-width')).toBe('true');

        warnSpy.mockRestore();
    });

    it('should pass the "resizeWidth" prop to the "matchReferenceWidth" property in mt-floating-ui with false', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper({
            props: {
                resizeWidth: false,
            },
        });

        const floatingUi = wrapper.findComponent({ name: 'mt-floating-ui' });
        expect(floatingUi.attributes('match-reference-width')).toBe('false');
    });

    it('should show deprecation warning when resizeWidth is used', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const warnSpy = jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementation();

        await createWrapper({
            props: {
                resizeWidth: true,
            },
        });

        expect(warnSpy).toHaveBeenCalledWith(
            'sw-popover',
            'The "resizeWidth" prop is deprecated and will be removed in v6.8.0. Please use "match-reference-width" instead.',
        );

        warnSpy.mockRestore();
    });

    it('should not show deprecation warning when resizeWidth is false', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const warnSpy = jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementation();

        await createWrapper({
            props: {
                resizeWidth: false,
            },
        });

        expect(warnSpy).not.toHaveBeenCalledWith(
            'sw-popover',
            'The "resizeWidth" prop is deprecated and will be removed in v6.8.0. Please use "match-reference-width" instead.',
        );

        warnSpy.mockRestore();
    });

    it('should prefer match-reference-width attribute over resizeWidth prop', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper({
            props: {
                resizeWidth: false,
            },
            attrs: {
                'match-reference-width': true,
            },
        });

        const floatingUi = wrapper.findComponent({ name: 'mt-floating-ui' });
        expect(floatingUi.attributes('match-reference-width')).toBe('true');
    });

    it('should prefer matchReferenceWidth camelCase attribute over resizeWidth prop', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper({
            props: {
                resizeWidth: false,
            },
            attrs: {
                matchReferenceWidth: true,
            },
        });

        const floatingUi = wrapper.findComponent({ name: 'mt-floating-ui' });
        expect(floatingUi.attributes('match-reference-width')).toBe('true');
    });

    it('should pass the "resizeWidth" prop to sw-popover-deprecated when feature flag is disabled and matchReferenceWidth is set', async () => {
        global.activeFeatureFlags = [''];

        const wrapper = await createWrapper({
            attrs: {
                matchReferenceWidth: true,
            },
        });

        const deprecatedPopover = wrapper.findComponent({ name: 'sw-popover-deprecated' });
        expect(deprecatedPopover.attributes('resize-width')).toBe('true');
    });

    it('should pass the "resizeWidth" prop to sw-popover-deprecated when feature flag is disabled and deprecated resizeWidth is set', async () => {
        global.activeFeatureFlags = [''];

        const wrapper = await createWrapper({
            attrs: {
                resizeWidth: true,
            },
        });

        const deprecatedPopover = wrapper.findComponent({ name: 'sw-popover-deprecated' });
        expect(deprecatedPopover.attributes('resize-width')).toBe('true');
    });
});
