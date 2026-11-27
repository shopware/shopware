/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { allowDeprecation, deprecations } from 'test/_helper_/deprecations';

const oldUsageDeprecation = 'The old usage of "sw-popover" is deprecated';
const resizeWidthDeprecation = 'The prop "resizeWidth" of the component';

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
    beforeEach(() => {
        allowDeprecation(oldUsageDeprecation);
        allowDeprecation(resizeWidthDeprecation);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-popover implementation.
    it.deprecated('v6.8.0.0')('should render the deprecated popover and report it as deprecated', async () => {
        const wrapper = await createWrapper();

        expect(deprecations).toHaveTriggered(oldUsageDeprecation);
        expect(wrapper.html()).toContain('sw-popover-deprecated');
        expect(wrapper.html()).not.toContain('mt-floating-ui');
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-popover implementation.
    it.deprecated('v6.8.0.0')('should report the resizeWidth prop as deprecated on the legacy popover', async () => {
        await createWrapper({
            props: {
                resizeWidth: true,
            },
        });

        expect(deprecations).toHaveTriggered(resizeWidthDeprecation);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render the mt-floating-ui', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-floating-ui');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should not report resizeWidth as deprecated when it is not passed', async () => {
        await createWrapper();

        expect(deprecations).not.toHaveTriggered(resizeWidthDeprecation);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should pass the match-reference-width attribute to mt-floating-ui', async () => {
        const wrapper = await createWrapper({
            attrs: {
                'match-reference-width': true,
            },
        });

        const floatingUi = wrapper.findComponent({ name: 'mt-floating-ui' });
        expect(floatingUi.attributes('match-reference-width')).toBe('true');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should pass the matchReferenceWidth camelCase attribute to mt-floating-ui',
        async () => {
            const wrapper = await createWrapper({
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
