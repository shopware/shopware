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
    // CHANGE REASON: This assertion covers the legacy sw-popover implementation removed when V6_8_0_0 ships. @removed @migrated
    // @deprecated tag:v6.8.0.0 - The test will be removed with the legacy sw-popover implementation.
    it.deprecated('v6.8.0.0')('should render the deprecated popover when major feature flag is disabled', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-popover-deprecated');
        expect(wrapper.html()).not.toContain('mt-floating-ui');
    });

    // CHANGE REASON: Scope the mt-floating-ui rendering branch to this test. @migrated
    it.activeFeatureFlags(['V6_8_0_0'])('should render the mt-floating-ui when major feature flag is enabled', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-floating-ui');
    });

    // CHANGE REASON: Run the enabled resizeWidth mapping assertion with declarative feature state. @migrated
    it.activeFeatureFlags(['V6_8_0_0'])(
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

    // CHANGE REASON: Keep the false resizeWidth mapping assertion independently feature-scoped. @migrated
    it.activeFeatureFlags(['V6_8_0_0'])(
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

    // CHANGE REASON: Declare the enabled deprecation-warning branch on the test itself. @migrated
    it.activeFeatureFlags(['V6_8_0_0'])('should show deprecation warning when resizeWidth is used', async () => {
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

    // CHANGE REASON: Keep the no-warning enabled-branch assertion isolated from neighboring tests. @migrated
    it.activeFeatureFlags(['V6_8_0_0'])('should not show deprecation warning when resizeWidth is false', async () => {
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

    // CHANGE REASON: Make enabled attribute precedence explicit without mutating globals. @migrated
    it.activeFeatureFlags(['V6_8_0_0'])('should prefer match-reference-width attribute over resizeWidth prop', async () => {
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

    // CHANGE REASON: Keep the camelCase precedence case declaratively feature-scoped. @migrated
    it.activeFeatureFlags(['V6_8_0_0'])(
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

    // CHANGE REASON: This compatibility assertion belongs to the legacy sw-popover branch removed in v6.8.0.0. @removed @migrated
    // @deprecated tag:v6.8.0.0 - The test will be removed with the legacy sw-popover implementation.
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

    // CHANGE REASON: This resizeWidth compatibility assertion belongs to the legacy sw-popover branch removed in v6.8.0.0. @removed @migrated
    // @deprecated tag:v6.8.0.0 - The test will be removed with the legacy sw-popover implementation.
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
