/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils_v3';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-block', {
        sync: true,
    }), {
        props: {
            block: {
                visibility: {
                    desktop: true,
                    tablet: true,
                    mobile: true,
                },
            },
        },
        global: {
            provide: {
                cmsService: {},
            },
            stubs: {
                'sw-icon': true,
                'sw-cms-visibility-toggle': {
                    template: '<div class="sw-cms-visibility-toggle-wrapper"></div>',
                },
            },
        },
    });
}
describe('module/sw-cms/component/sw-cms-block', () => {
    beforeEach(() => {
        if (Shopware.State.get('cmsPageState')) {
            Shopware.State.unregisterModule('cmsPageState');
        }

        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            state: {
                currentCmsDeviceView: 'desktop',
            },
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('the overlay should exist and be visible', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('.sw-cms-block__config-overlay').isVisible()).toBeTruthy();
    });

    it('the overlay should not exist', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true,
        });

        expect(wrapper.find('.sw-cms-block__config-overlay').exists()).toBeFalsy();
    });

    it('the visibility toggle wrapper should exist and be visible', async () => {
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

    it('the visibility toggle wrapper should not exist', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-visibility-toggle-wrapper').exists()).toBeFalsy();
    });
});
