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
    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-popover implementation.
    it.deprecated('v6.8.0.0')('should render the deprecated popover', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-popover-deprecated');
        expect(wrapper.html()).not.toContain('mt-floating-ui');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render the mt-floating-ui', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-floating-ui');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should pass the "resizeWidth" prop to the "matchReferenceWidth" property in mt-floating-ui with true',
        async () => {
            const warnSpy = jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementation();

            const wrapper = await createWrapper({
                props: {
                    resizeWidth: true,
                },
            });

            const floatingUi = wrapper.findComponent({ name: 'mt-floating-ui' });
            expect(floatingUi.attributes('match-reference-width')).toBe('true');

            warnSpy.mockRestore();
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should pass the "resizeWidth" prop to the "matchReferenceWidth" property in mt-floating-ui with false',
        async () => {
            const wrapper = await createWrapper({
                props: {
                    resizeWidth: false,
                },
            });

            const floatingUi = wrapper.findComponent({ name: 'mt-floating-ui' });
            expect(floatingUi.attributes('match-reference-width')).toBe('false');
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])('should show deprecation warning when resizeWidth is used', async () => {
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

    it.activeFeatureFlags(['v6.8.0.0'])('should not show deprecation warning when resizeWidth is false', async () => {
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

    it.activeFeatureFlags(['v6.8.0.0'])('should prefer match-reference-width attribute over resizeWidth prop', async () => {
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

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should prefer matchReferenceWidth camelCase attribute over resizeWidth prop',
        async () => {
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
        },
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-popover implementation.
    it.deprecated('v6.8.0.0')(
        'should pass the "resizeWidth" prop to sw-popover-deprecated when feature flag is disabled and matchReferenceWidth is set',
        async () => {
            const wrapper = await createWrapper({
                attrs: {
                    matchReferenceWidth: true,
                },
            });

            const deprecatedPopover = wrapper.findComponent({ name: 'sw-popover-deprecated' });
            expect(deprecatedPopover.attributes('resize-width')).toBe('true');
        },
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-popover implementation.
    it.deprecated('v6.8.0.0')(
        'should pass the "resizeWidth" prop to sw-popover-deprecated when feature flag is disabled and deprecated resizeWidth is set',
        async () => {
            const wrapper = await createWrapper({
                attrs: {
                    resizeWidth: true,
                },
            });

            const deprecatedPopover = wrapper.findComponent({ name: 'sw-popover-deprecated' });
            expect(deprecatedPopover.attributes('resize-width')).toBe('true');
        },
    );
});
