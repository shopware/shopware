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
    return mount(await wrapTestComponent('sw-cms-el-youtube-video', { sync: true }), {
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

describe('modules/sw-cms/elements/youtube-video/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/youtube-video');
    });

    it('displayModeClass computes classes for non-standard modes', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.displayModeClass).toBe('');

        const newElement = {
            ...defaultElement,
        };
        newElement.config.displayMode.value = 'full-width';

        await wrapper.setProps({
            element: newElement,
        });

        wrapper.vm.element.config.displayMode.value = 'full-width';
        expect(wrapper.vm.displayModeClass).toBe('is--full-width');
    });

    it('showControls computes URL parameters for showing the video controls', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.showControls).toBe('');

        const newElement = {
            ...defaultElement,
        };
        newElement.config.showControls.value = false;

        await wrapper.setProps({
            element: newElement,
        });

        expect(wrapper.vm.showControls).toBe('controls=0&');
    });

    it('loop computes URL parameters for looping the provided video', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.loop).toBe('');

        const newElement = {
            ...defaultElement,
        };
        newElement.config.loop.value = true;

        await wrapper.setProps({
            element: newElement,
        });

        expect(wrapper.vm.loop).toBe('loop=1&playlist=foo-bar&');
    });

    it('start computes URL parameters for starting the video at a specific time', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.start).toBe('');

        const newElement = {
            ...defaultElement,
        };
        newElement.config.start.value = 42;

        await wrapper.setProps({
            element: newElement,
        });

        expect(wrapper.vm.start).toBe('start=42&');
    });

    it('end computes URL parameters for ending the video at a specific time', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.end).toBe('');

        const newElement = {
            ...defaultElement,
        };
        newElement.config.end.value = 42;

        await wrapper.setProps({
            element: newElement,
        });

        expect(wrapper.vm.end).toBe('end=42&');
    });
});
