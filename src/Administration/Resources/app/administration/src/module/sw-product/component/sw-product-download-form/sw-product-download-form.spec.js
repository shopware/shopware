import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import swProductDownloadForm from 'src/module/sw-product/component/sw-product-download-form';
import 'src/app/component/base/sw-product-image';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/utils/sw-popover';

import EntityCollection from 'src/core/data/entity-collection.data';

Shopware.Component.register('sw-product-download-form', swProductDownloadForm);

async function createWrapper(privileges = [], hasError = false) {
    const localVue = createLocalVue();

    localVue.use(Vuex);
    localVue.directive('draggable', {});
    localVue.directive('droppable', {});
    localVue.directive('popover', {});

    return shallowMount(await Shopware.Component.build('sw-product-download-form'), {
        localVue,
        mocks: {
            $store: new Vuex.Store({
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
                                return hasError ? { code: 'some-error-code' } : null;
                            },
                        },
                    },
                },
            }),
        },
        computed: {
            error: () => {
                return hasError ? { code: 'some-error-code' } : null;
            },
        },
        provide: {
            repositoryFactory: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },
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
            'sw-product-image': await Shopware.Component.build('sw-product-image'),
            'sw-media-upload-v2': {
                template: '<div class="sw-media-upload-v2"></div>',
            },
            'sw-media-preview-v2': true,
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-icon': true,
            'sw-label': true,
            'sw-context-menu': await Shopware.Component.build('sw-context-menu'),
            'sw-context-menu-item': await Shopware.Component.build('sw-context-menu-item'),
            'sw-context-button': await Shopware.Component.build('sw-context-button'),
            'sw-field-error': true,
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
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the sw-media-upload-v2 component', async () => {
        const wrapper = await createWrapper([
            'product.editor',
        ]);

        expect(wrapper.find('.sw-media-upload-v2').exists()).toBeTruthy();
    });

    it('should not show the sw-media-upload-v2 component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-media-upload-v2').exists()).toBeFalsy();
    });

    it('should emit an event when onOpenMedia() function is called', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onOpenMedia();

        const pageChangeEvents = wrapper.emitted()['media-open'];
        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should show filename and metadata in the ui', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-product-download-form-row__name').text()).toBe('FileName.txt');
        expect(wrapper.find('.sw-product-download-form-row__mime').text()).toBe('plain/text');
        expect(wrapper.find('.sw-product-download-form-row__size').text()).toBe('1.00KB');
        expect(wrapper.find('.sw-product-download-form-row__changed-date').text()).toBe('08/02/2022, 13:00');
    });

    it('should accept only file extensions of the config service', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.fileAccept).toBe('png, svg, jpg, pdf');
    });

    it('should have an error state', async () => {
        const wrapper = await createWrapper(['product.editor'], true);

        expect(wrapper.find('.sw-product-download-form .sw-media-upload-v2').classes()).toContain('has--error');
    });
});
