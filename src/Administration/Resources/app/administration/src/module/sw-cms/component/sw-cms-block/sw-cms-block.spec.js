/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper(blockConfig = {}) {
    return mount(
        await wrapTestComponent('sw-cms-block', {
            sync: true,
        }),
        {
            props: {
                block: {
                    visibility: {
                        desktop: true,
                        tablet: true,
                        mobile: true,
                    },
                    ...blockConfig,
                },
            },
            global: {
                provide: {
                    cmsService: Shopware.Service().get('cmsService'),
                },
                stubs: {
                    'sw-icon': true,
                    'sw-cms-visibility-toggle': {
                        template: '<div class="sw-cms-visibility-toggle-wrapper"></div>',
                    },
                },
            },
        },
    );
}
describe('module/sw-cms/component/sw-cms-block', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have the overlay by default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('.sw-cms-block__config-overlay').isVisible()).toBeTruthy();
    });

    it('should not have the overlay when disabled', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true,
        });

        expect(wrapper.find('.sw-cms-block__config-overlay').exists()).toBeFalsy();
    });

    it('should have the visibility toggle wrapper, when set', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            block: {
                visibility: {
                    mobile: true,
                    tablet: true,
                    desktop: false,
                },
            },
        });

        expect(wrapper.find('.sw-cms-visibility-toggle-wrapper').exists()).toBeTruthy();
    });

    it('should not have the visibility toggle wrapper by default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-visibility-toggle-wrapper').exists()).toBeFalsy();
    });

    it('should be able to collapsed or expanded', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            block: {
                visibility: {
                    mobile: true,
                    tablet: true,
                    desktop: false,
                },
            },
        });

        expect(wrapper.get('.sw-cms-visibility-toggle-wrapper').classes()).not.toContain('is--expanded');
        wrapper.getComponent('.sw-cms-visibility-toggle-wrapper').vm.$emit('toggle');

        await wrapper.vm.$nextTick();
        expect(wrapper.get('.sw-cms-visibility-toggle-wrapper').classes()).toContain('is--expanded');
    });

    const errorClassCases = [
        { hasWarnings: false, hasErrors: false },
        { hasWarnings: false, hasErrors: true },
        { hasWarnings: true, hasErrors: false },
        { hasWarnings: true, hasErrors: true },
    ];
    it.each(errorClassCases)(
        'should merge custom CSS classes with error classes, when set in the block. [%s]',
        async ({ hasWarnings, hasErrors }) => {
            const wrapper = await createWrapper({
                cssClass: 'my-custom css-class in-the cms-block',
            });
            await wrapper.setProps({
                hasWarnings,
                hasErrors,
            });

            expect(wrapper.vm.customBlockClass).toStrictEqual({
                'has--warning': hasWarnings && !hasErrors,
                'has--error': hasErrors,
                'my-custom': true,
                'css-class': true,
                'in-the': true,
                'cms-block': true,
            });
        },
    );

    const exampleBackgroundMedia = { id: 'myMedia', url: 'example-image.png' };
    const exampleBackgroundMediaWithoutId = {
        id: undefined,
        url: 'example-image.png',
    };
    const blockStylesCases = [
        {
            backgroundColor: undefined,
            backgroundMedia: null,
            backgroundMediaMode: 'cover',
        },
        {
            backgroundColor: undefined,
            backgroundMedia: null,
            backgroundMediaMode: 'auto',
        },
        {
            backgroundColor: 'red',
            backgroundMedia: null,
            backgroundMediaMode: 'cover',
        },
        {
            backgroundColor: 'green',
            backgroundMedia: null,
            backgroundMediaMode: 'auto',
        },
        {
            backgroundColor: undefined,
            backgroundMedia: exampleBackgroundMedia,
            backgroundMediaMode: 'auto',
        },
        {
            backgroundColor: 'red',
            backgroundMedia: exampleBackgroundMedia,
            backgroundMediaMode: 'auto',
        },
        {
            backgroundColor: 'green',
            backgroundMedia: exampleBackgroundMedia,
            backgroundMediaMode: 'cover',
        },
        {
            backgroundColor: undefined,
            backgroundMedia: exampleBackgroundMediaWithoutId,
            backgroundMediaMode: 'cover',
        },
        {
            backgroundColor: 'red',
            backgroundMedia: exampleBackgroundMediaWithoutId,
            backgroundMediaMode: 'cover',
        },
        {
            backgroundColor: 'green',
            backgroundMedia: exampleBackgroundMediaWithoutId,
            backgroundMediaMode: 'auto',
        },
    ];
    it.each(blockStylesCases)('should apply backgroundMedia correctly to blockStyles. [%s]', async (expected) => {
        const wrapper = await createWrapper({
            backgroundColor: expected.backgroundColor,
            backgroundMedia: expected.backgroundMedia,
            backgroundMediaMode: expected.backgroundMediaMode,
        });

        const expectedBackgroundMedia = expected.backgroundMedia ? 'url("example-image.png")' : null;
        expect(wrapper.vm.blockStyles).toStrictEqual({
            'background-color': expected.backgroundColor || 'transparent',
            'background-image': expectedBackgroundMedia,
            'background-size': expected.backgroundMediaMode,
        });
    });

    it('should apply css modifier classes, when current block is active', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            active: true,
        });

        expect(wrapper.vm.overlayClasses).toStrictEqual({
            'is--active': true,
        });
        expect(wrapper.vm.toolbarClasses).toStrictEqual({
            'is--active': true,
        });
    });

    it('should emit a specific event, when the block overlay has been clicked', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-cms-block__config-overlay').trigger('click');
        expect(wrapper.emitted('block-overlay-click')).toBeTruthy();
    });

    it('should not emit a specific event, when the block overlay has been clicked, but the block is locked', async () => {
        const wrapper = await createWrapper({
            locked: true,
        });

        await wrapper.get('.sw-cms-block__config-overlay').trigger('click');
        expect(wrapper.emitted('block-overlay-click')).toBeFalsy();
    });
});
