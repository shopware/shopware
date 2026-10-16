/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const defaultElement = {
    type: 'vimeo-video',
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
        iframeTitle: {
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

    it('should embed the configured video with its title', async () => {
        const wrapper = await createWrapper();
        const iframe = wrapper.get('iframe');

        expect(iframe.attributes('src')).toContain('https://player.vimeo.com/video/');
        expect(iframe.attributes('src')).toContain('foo-bar?');
        expect(iframe.attributes('title')).toBe('foo-bar');
    });
});
