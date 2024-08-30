/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-cms-block-app-preview-renderer', { sync: true }), {
        ...additionalOptions,
    });
}

describe('src/module/sw-cms/blocks/app/app-renderer/preview/index.ts', () => {
    beforeEach(async () => {
        await flushPromises;
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render given preview image', async () => {
        const wrapper = await createWrapper({
            props: {
                block: {
                    appName: 'Best app ever',
                    label: 'My test label',
                    previewImage: 'preview-image.jpg',
                },
            },
        });

        expect(wrapper.find('img').attributes()).toEqual({
            src: 'preview-image.jpg',
            // THe alt attribute is defined with the block label
            alt: 'Preview image for app CMS block: My test label',
        });
    });

    it('should render fallback preview', async () => {
        const wrapper = await createWrapper({
            props: {
                block: {
                    appName: 'Best app ever',
                },
            },
        });

        expect(wrapper.find('img').exists()).toBeFalsy();
        expect(wrapper.find('.sw-cms-block-app-preview-renderer__fallback-preview').exists()).toBeTruthy();
        // Block name is not rendered in the fallback preview because
        // it is rendered by the CMS directly below the block preview
        expect(wrapper.find('.sw-cms-block-app-preview-renderer__fallback-preview').text()).toBe('Best app ever');
    });
});
