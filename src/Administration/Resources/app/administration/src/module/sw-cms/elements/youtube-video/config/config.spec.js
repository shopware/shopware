/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const defaultElementConfig = {
    content: {
        value: '',
    },
    start: {
        value: '',
    },
    end: {
        value: '',
    },
    videoID: {
        value: '',
    },
    previewMedia: {
        value: null,
    },
    autoPlay: {
        value: false,
    },
    loop: {
        value: false,
    },
    showControls: {
        value: true,
    },
    displayMode: {
        value: 'standard',
    },
    advancedPrivacyMode: {
        value: true,
    },
    needsConfirmation: {
        value: false,
    },
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-config-youtube-video', {
            sync: true,
        }),
        {
            props: {
                element: {
                    config: defaultElementConfig,
                },
            },
            global: {
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                },
                stubs: {
                    'sw-text-field': true,
                    'sw-switch-field': true,
                    'sw-select-field': true,
                    'sw-cms-mapping-field': true,
                    'sw-media-upload-v2': true,
                    'sw-alert': true,
                    'sw-upload-listener': true,
                    'sw-media-modal-v2': true,
                },
            },
        },
    );
}

describe('modules/sw-cms/elements/youtube-video/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/youtube-video');
    });

    it('should get the ID from the share link', async () => {
        const wrapper = await createWrapper();
        const shortLink = wrapper.vm.shortenLink('https://youtu.be/Bey4XXJAqS8');

        expect(shortLink).toBe('Bey4XXJAqS8');
    });

    it('should get the ID from the share link with starting point', async () => {
        const wrapper = await createWrapper();
        const shortLink = wrapper.vm.shortenLink('https://youtu.be/Bey4XXJAqS8?t=1');

        expect(shortLink).toBe('Bey4XXJAqS8');
    });

    it('should get the ID from the url', async () => {
        const wrapper = await createWrapper();
        const shortLink = wrapper.vm.shortenLink('https://www.youtube.com/watch?v=Bey4XXJAqS8');

        expect(shortLink).toBe('Bey4XXJAqS8');
    });

    it('should get the ID from the url with starting point', async () => {
        const wrapper = await createWrapper();
        const shortLink = wrapper.vm.shortenLink('https://www.youtube.com/watch?v=Bey4XXJAqS8');

        expect(shortLink).toBe('Bey4XXJAqS8');
    });

    it('should convert time to url format', async () => {
        const wrapper = await createWrapper();
        const convertedTime = wrapper.vm.convertTimeToUrlFormat('20:33');

        expect(convertedTime.minutes).toBe(20);
        expect(convertedTime.seconds).toBe(33);
        expect(convertedTime.string).toBe(1233);
    });

    it('should convert time to input format', async () => {
        const wrapper = await createWrapper();
        const convertedTime = wrapper.vm.convertTimeToInputFormat(2077);

        expect(convertedTime.seconds).toBe(37);
        expect(convertedTime.minutes).toBe(34);
        expect(convertedTime.string).toBe('34:37');
    });

    it('should set a fallback value if user types no valid time', async () => {
        const wrapper = await createWrapper();
        const userInput = wrapper.vm.convertTimeToInputFormat('aaaahhhhh');

        expect(userInput.seconds).toBe(0);
        expect(userInput.minutes).toBe(0);
        expect(userInput.string).toBe('0:00');
    });

    it('videoID setter calls shortenLink function to format input', async () => {
        const wrapper = await createWrapper();
        const shortenLinkSpy = jest.spyOn(wrapper.vm, 'shortenLink');

        wrapper.vm.videoID = 'https://www.youtube.com/watch?v=1234567890';
        expect(shortenLinkSpy).toHaveBeenCalledWith('https://www.youtube.com/watch?v=1234567890');
    });

    it('should compute previewSource based on either the configured value or a default config', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.previewSource).toBe(defaultElementConfig.previewMedia.value);

        await wrapper.setProps({
            element: {
                config: defaultElementConfig,
                data: {
                    previewMedia: {
                        id: 'foo-bar',
                    },
                },
            },
        });

        expect(wrapper.vm.previewSource).toEqual({ id: 'foo-bar' });

        await wrapper.setProps({
            element: {
                config: defaultElementConfig,
                data: {
                    previewMedia: {
                        id: 'bar-foo',
                    },
                },
            },
        });
        expect(wrapper.vm.previewSource).toEqual({ id: 'bar-foo' });
    });
});
