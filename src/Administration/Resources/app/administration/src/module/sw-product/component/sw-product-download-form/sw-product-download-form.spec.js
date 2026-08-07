import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import EntityCollection from 'src/core/data/entity-collection.data';

const originalCreateObjectURL = window.URL.createObjectURL;
const originalRevokeObjectURL = window.URL.revokeObjectURL;

/**
 * @sw-package inventory
 */
async function createWrapper(hasError = false, mediaService = {}, configSettings = {}) {
    if (hasError) {
        Shopware.Store.get('error').addApiError({
            expression: 'product.product1Id.downloads',
            error: {
                detail: 'This is an error',
            },
        });
    }

    return mount(await wrapTestComponent('sw-product-download-form', { sync: true }), {
        global: {
            mocks: {
                $store: createStore({
                    modules: {
                        swProductDetail: {
                            namespaced: true,
                            getters: {
                                isLoading: () => false,
                            },
                        },
                    },
                }),
            },
            provide: {
                repositoryFactory: {},
                configService: {
                    getConfig() {
                        return Promise.resolve({
                            settings: {
                                private_allowed_extensions: [
                                    'png',
                                    'svg',
                                    'jpg',
                                    'pdf',
                                ],
                                private_allowed_mime_types_by_extension: {
                                    pdf: ['application/pdf'],
                                    epub: ['application/epub+zip'],
                                },
                                ...configSettings,
                            },
                        });
                    },
                },
                mediaService: {
                    prepareDownloadMedia: jest.fn(),
                    downloadMedia: jest.fn(),
                    ...mediaService,
                },
            },
            stubs: {
                'sw-upload-listener': true,
                'sw-product-image': await wrapTestComponent('sw-product-image'),
                'sw-media-upload-v2': {
                    name: 'sw-media-upload-v2',
                    props: [
                        'extensionAccept',
                        'extensionMimeTypesByExtension',
                    ],
                    template: '<div class="sw-media-upload-v2"></div>',
                },
                'sw-media-preview-v2': true,
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-label': true,
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-field-error': true,
                'sw-loader': true,
            },
            directives: {
                draggable: {},
                droppable: {},
                popover: {},
            },
        },
    });
}

const files = [
    {
        mediaId: 'media1',
        position: 0,
        id: 'productMedia1',
        media: {
            id: 'media1',
            fileName: 'FileName',
            mimeType: 'plain/text',
            fileExtension: 'txt',
            fileSize: 1024, // 1KB
            createdAt: new Date('02/08/2022, 13:00'),
        },
    },
];

function getFileCollection(collection = []) {
    return new EntityCollection('/media', 'media', null, { isShopwareContext: true }, collection, collection.length, null);
}

describe('module/sw-product/component/sw-product-download-form', () => {
    beforeAll(() => {
        const product = {
            downloads: getFileCollection(files),
            id: 'product1Id',
        };
        product.getEntityName = () => 'product';

        Shopware.Store.get('swProductDetail').product = product;
    });

    beforeEach(() => {
        // Reset all mocks
        jest.clearAllMocks();

        // Reset error state
        Shopware.Store.get('error').system = {};
        Shopware.Store.get('error').api = {};
    });

    afterEach(() => {
        jest.restoreAllMocks();
        Object.defineProperty(window.URL, 'createObjectURL', {
            configurable: true,
            writable: true,
            value: originalCreateObjectURL,
        });
        Object.defineProperty(window.URL, 'revokeObjectURL', {
            configurable: true,
            writable: true,
            value: originalRevokeObjectURL,
        });
    });

    it('should show the sw-media-upload-v2 component', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-media-upload-v2').exists()).toBeTruthy();
    });

    it('should not show the sw-media-upload-v2 component', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-media-upload-v2').exists()).toBeFalsy();
    });

    it('should emit an event when onOpenMedia() function is called', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onOpenMedia();

        const pageChangeEvents = wrapper.emitted()['media-open'];
        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should show filename and metadata in the ui', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-product-download-form-row__name').text()).toBe('FileName.txt');
        expect(wrapper.find('.sw-product-download-form-row__mime').text()).toBe('plain/text');
        expect(wrapper.find('.sw-product-download-form-row__size').text()).toBe('1.00KB');
        expect(wrapper.find('.sw-product-download-form-row__changed-date').text()).toBe('08/02/2022, 13:00');
    });

    it('should accept only file extensions of the config service', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.fileAccept).toBe('png, svg, jpg, pdf');
    });

    it('should read private mime types by extension from the config service', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.fileAcceptedMimeTypesByExtension).toEqual({
            pdf: ['application/pdf'],
            epub: ['application/epub+zip'],
        });
    });

    it('should pass accepted extensions and mime types to the media upload component', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        const mediaUpload = wrapper.getComponent({ name: 'sw-media-upload-v2' });

        expect(mediaUpload.props('extensionAccept')).toBe('png, svg, jpg, pdf');
        expect(mediaUpload.props('extensionMimeTypesByExtension')).toEqual({
            pdf: ['application/pdf'],
            epub: ['application/epub+zip'],
        });
    });

    it('should have an error state', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper(true);
        await flushPromises();

        expect(wrapper.find('.sw-product-download-form .sw-media-upload-v2').classes()).toContain('has--error');
    });

    it('should download a private product media file via the media service', async () => {
        const mediaBlob = new Blob(['download-content']);
        const prepareDownloadMediaMock = jest.fn().mockResolvedValue({ type: 'blob' });
        const downloadMediaMock = jest.fn().mockResolvedValue(mediaBlob);
        const objectUrl = 'blob:product-download';
        const createObjectURLMock = jest.fn().mockReturnValue(objectUrl);
        const revokeObjectURLMock = jest.fn();
        const originalCreateElement = document.createElement.bind(document);
        const link = document.createElement('a');
        const createElementSpy = jest.spyOn(document, 'createElement').mockImplementation((tagName, ...args) => {
            if (tagName === 'a') {
                return link;
            }

            return originalCreateElement(tagName, ...args);
        });
        const dispatchEventSpy = jest.spyOn(link, 'dispatchEvent').mockImplementation(() => true);
        const removeSpy = jest.spyOn(link, 'remove').mockImplementation(() => {});

        Object.defineProperty(window.URL, 'createObjectURL', {
            configurable: true,
            writable: true,
            value: createObjectURLMock,
        });
        Object.defineProperty(window.URL, 'revokeObjectURL', {
            configurable: true,
            writable: true,
            value: revokeObjectURLMock,
        });

        const wrapper = await createWrapper(false, {
            prepareDownloadMedia: prepareDownloadMediaMock,
            downloadMedia: downloadMediaMock,
        });

        await wrapper.vm.downloadMedia({
            media: {
                id: 'media-1',
                fileName: 'Private download',
                fileExtension: 'pdf',
            },
        });

        await flushPromises();

        expect(prepareDownloadMediaMock).toHaveBeenCalledWith('media-1');
        expect(downloadMediaMock).toHaveBeenCalledWith('media-1');
        expect(createObjectURLMock).toHaveBeenCalledWith(mediaBlob);
        expect(createElementSpy).toHaveBeenCalledWith('a');
        expect(link.href).toBe(objectUrl);
        expect(link.download).toBe('Private download.pdf');
        expect(dispatchEventSpy).toHaveBeenCalledWith(expect.any(MouseEvent));
        expect(removeSpy).toHaveBeenCalled();
        expect(revokeObjectURLMock).toHaveBeenCalledWith(objectUrl);
    });

    it('should directly trigger external product media downloads', async () => {
        const prepareDownloadMediaMock = jest.fn().mockResolvedValue({
            type: 'external',
            url: 'https://cdn.example.test/download',
        });
        const downloadMediaMock = jest.fn();
        const createObjectURLMock = jest.fn();
        const originalCreateElement = document.createElement.bind(document);
        const link = document.createElement('a');
        const createElementSpy = jest.spyOn(document, 'createElement').mockImplementation((tagName, ...args) => {
            if (tagName === 'a') {
                return link;
            }

            return originalCreateElement(tagName, ...args);
        });
        const dispatchEventSpy = jest.spyOn(link, 'dispatchEvent').mockImplementation(() => true);
        const removeSpy = jest.spyOn(link, 'remove').mockImplementation(() => {});

        Object.defineProperty(window.URL, 'createObjectURL', {
            configurable: true,
            writable: true,
            value: createObjectURLMock,
        });

        const wrapper = await createWrapper(false, {
            prepareDownloadMedia: prepareDownloadMediaMock,
            downloadMedia: downloadMediaMock,
        });

        await wrapper.vm.downloadMedia({
            media: {
                id: 'media-1',
                fileName: 'Private download',
                fileExtension: 'pdf',
            },
        });

        await flushPromises();

        expect(prepareDownloadMediaMock).toHaveBeenCalledWith('media-1');
        expect(downloadMediaMock).not.toHaveBeenCalled();
        expect(createObjectURLMock).not.toHaveBeenCalled();
        expect(createElementSpy).toHaveBeenCalledWith('a');
        expect(link.href).toBe('https://cdn.example.test/download');
        expect(link.download).toBe('');
        expect(link.target).toBe('_blank');
        expect(link.rel).toBe('noopener noreferrer');
        expect(dispatchEventSpy).toHaveBeenCalledWith(expect.any(MouseEvent));
        expect(removeSpy).toHaveBeenCalled();
    });
});
