/**
 * @package content
 */
import { mount } from '@vue/test-utils';
import { deepMergeObject } from 'src/core/service/utils/object.utils';

describe('src/app/asyncComponent/media/sw-media-preview-v2', () => {
    const createWrapper = async (componentConfig = {}) => {
        const config = {
            props: {
                source: {
                    fileName: 'example',
                    fileExtension: 'jpg',
                },
            },
            global: {
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
            },
        };

        return mount(
            await wrapTestComponent('sw-media-preview-v2', { sync: true }),
            deepMergeObject(config, componentConfig),
        );
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

        expect(`${wrapper.vm.$options.placeholderThumbnailsBasePath}icons-multicolor-file-thumbnail-broken.svg`).toContain(
            wrapper.find('.sw-media-preview-v2__item').attributes('src'),
        );
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

        expect(`${wrapper.vm.$options.placeholderThumbnailsBasePath}icons-multicolor-file-thumbnail-normal.svg`).toContain(
            wrapper.find('.sw-media-preview-v2__item').attributes('src'),
        );
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
            'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                'icons-multicolor-file-thumbnail-ppt',
            'video/x-msvideo': 'icons-multicolor-file-thumbnail-avi',
            'video/quicktime': 'icons-multicolor-file-thumbnail-mov',
            'video/mp4': 'icons-multicolor-file-thumbnail-mp4',
            'text/csv': 'icons-multicolor-file-thumbnail-csv',
            'text/plain': 'icons-multicolor-file-thumbnail-csv',
            'image/gif': 'icons-multicolor-file-thumbnail-gif',
            'image/jpeg': 'icons-multicolor-file-thumbnail-jpg',
            'image/svg+xml': 'icons-multicolor-file-thumbnail-svg',
            'model/gltf-binary': 'icons-multicolor-file-thumbnail-glb',
            unknown: 'icons-multicolor-file-thumbnail-normal',
        };

        await Promise.all(
            Object.keys(fileTypes).map(async (type) => {
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

                expect(`${wrapper.vm.$options.placeholderThumbnailsBasePath}${fileTypes[type]}.svg`).toContain(
                    wrapper.find('.sw-media-preview-v2__item').attributes('src'),
                );
            }),
        );
    });

    it('should handle relative path sources', async () => {
        const wrapper = await createWrapper({
            props: {
                source: '/bundles/administration/static/img/cms/preview_mountain_large.jpg',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            get: () => {
                                return Promise.reject();
                            },
                        }),
                    },
                },
            },
        });

        expect(wrapper.vm.trueSource).toEqual(wrapper.vm.source);
    });

    it('should handle UUID sources', async () => {
        const expectedFile = {
            fileName: 'example',
            fileExtension: 'jpg',
        };

        const wrapper = await createWrapper({
            props: {
                source: '0dbfb95b662a410f9ca134f8f2a60d5e',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            get: () => {
                                return Promise.resolve(expectedFile);
                            },
                        }),
                    },
                },
            },
        });

        expect(wrapper.vm.trueSource).toEqual(expectedFile);
    });

    it('previewUrl function should handle relative paths', async () => {
        const wrapper = await createWrapper({
            props: {
                source: '/bundles/administration/static/img/cms/preview_mountain_large.jpg',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            get: () => {
                                return Promise.reject();
                            },
                        }),
                    },
                },
            },
        });

        expect(wrapper.vm.previewUrl).toEqual(wrapper.vm.source);
    });
});
