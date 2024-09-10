/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const defaultElement = {
    config: {
        videoID: {
            value: 'foo-bar',
        },
    },
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-config-vimeo-video', { sync: true }), {
        props: {
            element: defaultElement,
        },
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
            stubs: {
                'sw-text-field': true,
                'sw-switch-field': true,
                'sw-colorpicker': true,
                'sw-cms-mapping-field': true,
                'sw-media-upload-v2': true,
                'sw-media-modal-v2': true,
                'sw-alert': true,
                'sw-upload-listener': true,

            },
        },
    });
}

describe('modules/sw-cms/elements/vimeo-video/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/vimeo-video');
    });

    it('should get the video ID from the vimeo link', async () => {
        const wrapper = await createWrapper();
        const shortenLink = wrapper.vm.shortenLink('https://vimeo.com/255024952');

        expect(shortenLink).toBe('255024952');
    });

    it('should get the video ID from the vimeo link with a timestamp', async () => {
        const wrapper = await createWrapper();
        const shortenLink = wrapper.vm.shortenLink('https://vimeo.com/282340616#t=120s');

        expect(shortenLink).toBe('282340616');
    });
});
