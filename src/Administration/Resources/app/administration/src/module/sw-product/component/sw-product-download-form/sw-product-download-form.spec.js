import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package inventory
 */
async function createWrapper(hasError = false) {
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
                        error: {
                            namespaced: true,
                            getters: {
                                getApiError: () => {
                                    return () => {
                                        return hasError ? { code: 'some-error-code' } : null;
                                    };
                                },
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
                            },
                        });
                    },
                },
            },
            stubs: {
                'sw-upload-listener': true,
                'sw-product-image': await wrapTestComponent('sw-product-image'),
                'sw-media-upload-v2': {
                    template: '<div class="sw-media-upload-v2"></div>',
                },
                'sw-media-preview-v2': true,
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-icon': true,
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
    return new EntityCollection(
        '/media',
        'media',
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null,
    );
}

describe('module/sw-product/component/sw-product-download-form', () => {
    beforeAll(() => {
        const product = {
            downloads: getFileCollection(files),
        };
        product.getEntityName = () => 'T-Shirt';

        Shopware.State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: product,
            },
        });
    });

    it('should be a Vue.JS component', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
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

    it('should have an error state', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper(true);
        await flushPromises();

        expect(wrapper.find('.sw-product-download-form .sw-media-upload-v2').classes()).toContain('has--error');
    });
});
