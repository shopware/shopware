/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-image', { sync: true }), {
        global: {
            stubs: {
                'sw-label': {
                    template: `
                        <div class="sw-label">
                            <slot></slot>
                        </div>`,
                },
                'sw-context-button': {
                    template: `
                        <div class="sw-context-button">
                            <slot></slot>
                        </div>`,
                },
                'sw-context-menu-item': true,
                'sw-icon': true,
                'sw-media-preview-v2': true,
            },
        },
        props: {
            mediaId: 'b849df93c8bb4c7a94441fb0e82be516',
        },
    });
}

describe('app/component/base/sw-product-image', () => {
    it('should display button to set as cover image if media is not a 3D object and is not already a cover image', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            showCoverLabel: true,
        });

        const setAsCoverButton = wrapper.find('.sw-product-image__context-button .sw-product-image__button-cover');
        expect(setAsCoverButton.exists()).toBe(true);
    });

    it('should not display button to set as cover image if media is not a 3D object and is already a cover image', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            showCoverLabel: true,
            isCover: true,
        });

        const setAsCoverButton = wrapper.find('.sw-product-image__context-button .sw-product-image__button-cover');
        expect(setAsCoverButton.exists()).toBe(false);
    });

    it('should not show spatial label if media item is not a 3D object', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            isSpatial: true,
        });

        const setAsCoverButton = wrapper.find('.sw-product-image__spatial-label .sw-product-image__button-cover');
        expect(setAsCoverButton.exists()).toBe(false);
    });

    it('should show spatial label if media item is a 3D object', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            isSpatial: true,
        });

        const setAsCoverButton = wrapper.find('.sw-product-image__spatial-label .sw-label__spatial');
        expect(setAsCoverButton.exists()).toBe(true);
    });

    it('should should AR ready label if media item is a 3D object and has AR enabled', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            isSpatial: true,
            isArReady: true,
        });

        const setAsCoverButton = wrapper.find('.sw-product-image__spatial-label .sw-label__ar-ready');
        expect(setAsCoverButton.exists()).toBe(true);
    });
});
