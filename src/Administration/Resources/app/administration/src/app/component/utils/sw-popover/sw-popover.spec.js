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

    it.activeFeatureFlags(['v6.8.0.0'])('should throw when the deprecated "resizeWidth" prop is true', async () => {
        await expect(
            createWrapper({
                props: {
                    resizeWidth: true,
                },
            }),
        ).rejects.toThrow('Tried to access deprecated functionality');
    });

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

    it.activeFeatureFlags(['v6.8.0.0'])('should not show deprecation warning when resizeWidth is false', async () => {
        const deprecationSpy = jest.spyOn(Shopware.Feature, 'triggerDeprecationOrThrow');

        await createWrapper({
            props: {
                resizeWidth: false,
            },
        });

        expect(deprecationSpy).not.toHaveBeenCalled();
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
