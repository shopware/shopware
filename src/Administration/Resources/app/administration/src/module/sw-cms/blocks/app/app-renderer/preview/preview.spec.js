/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(props = undefined) {
    return mount(
        await wrapTestComponent('sw-cms-block-app-preview-renderer', {
            sync: true,
        }),
        {
            props,

            global: {
                stubs: {
                    'sw-extension-teaser-popover': true,
                },
            },
        },
    );
}

describe('src/module/sw-cms/blocks/app/app-renderer/preview/sw-cms-block-app-preview-renderer', () => {
    it('should provide defaults if props are missing', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeDefined();
        expect(wrapper.vm.block).toEqual({});
        expect(wrapper.vm.previewImage).toBeUndefined();
        expect(wrapper.vm.blockLabel).toBe('');
        expect(wrapper.vm.appName).toBe('');
    });

    it('should render given preview image', async () => {
        const wrapper = await createWrapper({
            block: {
                appName: 'Best app ever',
                label: 'My test label',
                previewImage: 'preview-image.jpg',
            },
        });

        expect(wrapper.find('img').attributes()).toEqual({
            src: 'preview-image.jpg',
            alt: 'Preview image for app CMS block: My test label',
        });
    });

    it('should render fallback preview', async () => {
        const wrapper = await createWrapper({
            block: {
                appName: 'Best app ever',
            },
        });

        expect(wrapper.find('img').exists()).toBeFalsy();
        expect(wrapper.find('.sw-cms-block-app-preview-renderer__fallback-preview').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-block-app-preview-renderer__fallback-preview').text()).toBe('Best app ever');
    });
});
