import { createLocalVue, shallowMount } from '@vue/test-utils';
import swMediaPreviewV2 from 'src/app/asyncComponent/media/sw-media-preview-v2';
import 'src/app/component/base/sw-icon';

Shopware.Component.register('sw-media-preview-v2', swMediaPreviewV2);

describe('src/app/asyncComponent/media/sw-media-preview-v2', () => {
    const createWrapper = async () => {
        const localVue = createLocalVue();

        return shallowMount(await Shopware.Component.build('sw-media-preview-v2'), {
            localVue,
            stubs: {
                'sw-icon': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return Promise.resolve();
                        },
                        get: () => {
                            return Promise.resolve();
                        },
                        search: () => {
                            return Promise.resolve();
                        },
                    }),
                },
            },
            propsData: {
                source: {
                    fileName: 'example',
                    fileExtension: 'jpg',
                },
            },
        });
    };

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render broken icon when file type is unknown', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            mediaIsPrivate: false,
        });
        await wrapper.setData({
            imagePreviewFailed: true,
            trueSource: { mimeType: null, thumbnails: [] },
        });
        await flushPromises();
        wrapper.vm.showEvent();
        await flushPromises();

        expect(`${wrapper.vm.$options.placeholderThumbnailsBasePath}icons-multicolor-file-thumbnail-broken.svg`).toContain(wrapper.find('.sw-media-preview-v2__item').attributes('src'));
    });

    it('should render normal icon when image preview failed and media is private', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            mediaIsPrivate: true,
        });
        await wrapper.setData({
            imagePreviewFailed: true,
            trueSource: { mimeType: 'image/jpg', thumbnails: [] },
        });
        await flushPromises();
        wrapper.vm.showEvent();
        await flushPromises();

        expect(`${wrapper.vm.$options.placeholderThumbnailsBasePath}icons-multicolor-file-thumbnail-normal.svg`).toContain(wrapper.find('.sw-media-preview-v2__item').attributes('src'));
    });

    it('should render lock icon when width is greater than 40px', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            mediaIsPrivate: true,
        });
        await wrapper.setData({
            imagePreviewFailed: true,
            trueSource: { mimeType: 'image/jpg', thumbnails: [] },
            width: 41,
        });
        await flushPromises();
        wrapper.vm.showEvent();
        await flushPromises();

        expect(wrapper.find('.sw-media-preview-v2__locked-icon').exists()).toBe(true);
    });

    it('should not render lock icon when width is smaller 40px', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            mediaIsPrivate: true,
        });
        await wrapper.setData({
            imagePreviewFailed: true,
            trueSource: { mimeType: 'image/jpg', thumbnails: [] },
            width: 39,
        });
        await flushPromises();
        wrapper.vm.showEvent();
        await flushPromises();

        expect(wrapper.find('.sw-media-preview-v2__locked-icon').exists()).toBe(false);
    });

    it('should render correct icon for all defined file types', async () => {
        const fileTypes = {
            'application/adobe.illustrator': 'icons-multicolor-file-thumbnail-ai',
            'application/illustrator': 'icons-multicolor-file-thumbnail-ai',
            'application/postscript': 'icons-multicolor-file-thumbnail-ai',
            'application/msword': 'icons-multicolor-file-thumbnail-doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'icons-multicolor-file-thumbnail-doc',
            'application/pdf': 'icons-multicolor-file-thumbnail-pdf',
            'application/vnd.ms-excel': 'icons-multicolor-file-thumbnail-xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'icons-multicolor-file-thumbnail-xls',
            'application/vnd.ms-powerpoint': 'icons-multicolor-file-thumbnail-ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'icons-multicolor-file-thumbnail-ppt',
            'video/x-msvideo': 'icons-multicolor-file-thumbnail-avi',
            'video/quicktime': 'icons-multicolor-file-thumbnail-mov',
            'video/mp4': 'icons-multicolor-file-thumbnail-mp4',
            'text/csv': 'icons-multicolor-file-thumbnail-csv',
            'text/plain': 'icons-multicolor-file-thumbnail-csv',
            'image/gif': 'icons-multicolor-file-thumbnail-gif',
            'image/jpeg': 'icons-multicolor-file-thumbnail-jpg',
            'image/svg+xml': 'icons-multicolor-file-thumbnail-svg',
            unknown: 'icons-multicolor-file-thumbnail-normal',
        };

        await Promise.all(Object.keys(fileTypes).map(async (type) => {
            const wrapper = await createWrapper();
            await wrapper.setProps({
                mediaIsPrivate: true,
            });
            await wrapper.setData({
                imagePreviewFailed: true,
                trueSource: { mimeType: type, thumbnails: [] },
            });
            await flushPromises();
            wrapper.vm.showEvent();
            await flushPromises();

            expect(`${wrapper.vm.$options.placeholderThumbnailsBasePath}${fileTypes[type]}.svg`).toContain(wrapper.find('.sw-media-preview-v2__item').attributes('src'));
        }));
    });
});
