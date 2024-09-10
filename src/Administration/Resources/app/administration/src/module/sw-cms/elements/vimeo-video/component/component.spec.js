/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const defaultElement = {
    config: {
        displayMode: {
            value: 'standard',
        },
        content: {
            value: '',
        },
        start: {
            value: 0,
        },
        end: {
            value: 0,
        },
        videoID: {
            value: 'foo-bar',
        },
        showControls: {
            value: true,
        },
        loop: {
            value: false,
        },
        previewMedia: {
            value: '',
        },
    },
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-vimeo-video', { sync: true }), {
        props: {
            element: defaultElement,
        },
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
        },
    });
}

describe('modules/sw-cms/elements/vimeo-video/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/vimeo-video');
    });

    it('mounts the component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
