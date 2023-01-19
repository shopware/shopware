import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/component/sw-product-variants/sw-product-variants-overview';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/media/sw-media-upload-v2';
import 'src/app/component/media/sw-media-compact-upload-v2';
import 'src/app/component/form/sw-checkbox-field';
import Vuex from 'vuex';

/*
 * @package inventory
 */
async function createWrapper(propsOverrides = {}, repositoryFactoryOverride = {}) {
    const localVue = createLocalVue();
    localVue.use(Vuex);
    return shallowMount(await Shopware.Component.build('sw-product-variants-overview'), {
        localVue,
        propsData: {
            selectedGroups: [],
            uploadTag: 'uploadTag',
            ...propsOverrides
        },
        mocks: {
            $route: {
                query: {}
            },
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve(),
                    save: () => Promise.resolve([]),
                    get: () => Promise.resolve({}),
                }),
                ...repositoryFactoryOverride
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }
                    return global.activeAclRoles.includes(identifier);
                }
            },
            searchRankingService: {},
            configService: {
                getConfig: () => Promise.resolve({
                    settings: {
                        enableUrlFeature: false
                    },
                })
            },
            mediaService: {
                addListener: () => {},
                removeByTag: () => {},
                removeListener: () => {},
            }
        },
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-simple-search-field': true,
            'sw-button': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>'
            },
            'sw-icon': true,
            'sw-context-menu': true,
            'sw-tree': true,
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-context-menu-item': {
                template: '<div class="sw-context-menu-item" @click="$emit(\'click\')"><slot></slot></div>'
            },
            'sw-pagination': true,
            'sw-modal': {
                template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>'
            },
            'sw-data-grid-settings': true,
            'sw-context-button': true,
            'sw-product-variants-media-upload': true,
            'sw-inheritance-switch': true,
            'router-link': true,
            'sw-media-compact-upload-v2': await Shopware.Component.build('sw-media-compact-upload-v2'),
            'sw-upload-listener': true,
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-field-error': true,
            'sw-base-field': true,
            'sw-context-menu-divider': true,
            'sw-media-preview-v2': true,
        },
    });
}

describe('src/module/sw-product/component/sw-product-variants/sw-product-variants-overview', () => {
    beforeEach(async () => {
        global.activeAclRoles = [];

        const product = {
            media: []
        };
        product.getEntityName = () => 'T-Shirt';

        if (Shopware.State.get('swProductDetail')) {
            Shopware.State.unregisterModule('swProductDetail');
        }

        Shopware.State.registerModule('swProductDetail', {
            namespaced: true,
            state() {
                return {
                    product: product,
                    currencies: [],
                    variants: [
                        {
                            id: 1,
                            productNumber: '1',
                            name: null,
                            options: [
                                {
                                    id: 1,
                                    name: '30',
                                    translated: {
                                        name: '30',
                                    },
                                    groupId: 'size-group-id',
                                },
                            ],
                        },
                        {
                            id: 2,
                            productNumber: '2',
                            name: null,
                            options: [
                                {
                                    id: 2,
                                    name: '32',
                                    translated: {
                                        name: '32',
                                    },
                                    groupId: 'size-group-id',
                                },
                            ],
                        },
                    ],
                    taxes: [],
                };
            },
            getters: {
                isLoading: () => false
            },
            mutations: {
                setVariants(state, variants) {
                    state.variants = variants;
                },
                setLoading() {}
            },
        });
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled generate variants button', async () => {
        const wrapper = await createWrapper();
        const generateVariantsButton = wrapper.find('.sw-product-variants__generate-action');
        expect(generateVariantsButton.exists()).toBeTruthy();
        expect(generateVariantsButton.attributes().disabled).toBeTruthy();
    });

    it('should have an enabled generate variants button', async () => {
        global.activeAclRoles = ['product.creator'];

        const wrapper = await createWrapper();
        const generateVariantsButton = wrapper.find('.sw-product-variants__generate-action');
        expect(generateVariantsButton.exists()).toBeTruthy();
        expect(generateVariantsButton.attributes().disabled).toBeFalsy();
    });

    it('should allow inline editing', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper();
        const dataGrid = wrapper.find('.sw-product-variants-overview__data-grid');
        expect(dataGrid.props('allowInlineEdit')).toBe(true);
    });

    it('should disallow inline editing', async () => {
        const wrapper = await createWrapper();
        const dataGrid = wrapper.find('.sw-product-variants-overview__data-grid');
        expect(dataGrid.props('allowInlineEdit')).toBe(false);
    });

    it('should enable selection deleting of list variants', async () => {
        global.activeAclRoles = ['product.deleter'];

        const wrapper = await createWrapper();
        const dataGrid = wrapper.find('.sw-product-variants-overview__data-grid');
        expect(dataGrid.props('showSelection')).toBe(true);
    });

    it('should be able to turn on delete confirmation modal', async () => {
        global.activeAclRoles = ['product.deleter'];

        const wrapper = await createWrapper();

        const deleteContextButton = wrapper.find('.sw-context-menu-item[variant="danger"]');
        await deleteContextButton.trigger('click');

        await wrapper.vm.$forceUpdate();

        const deleteModal = wrapper.find('.sw-product-variants-overview__delete-modal');
        expect(deleteModal.exists()).toBeTruthy();

        expect(wrapper.find('.sw-product-variants-overview__modal--confirm-delete-text').text())
            .toBe('sw-product.variations.generatedListDeleteModalMessage');
    });

    it('should not be able to turn on delete confirmation modal', async () => {
        global.activeAclRoles = ['product.editor'];

        const wrapper = await createWrapper();

        const deleteContextButton = wrapper.find('.sw-context-menu-item[variant="danger"]');
        expect(deleteContextButton.attributes().disabled).toBe('disabled');
    });

    it('should be able to delete variants', async () => {
        global.activeAclRoles = ['product.deleter'];

        const wrapper = await createWrapper();

        const selectAllInput = wrapper.find('.sw-data-grid__select-all input[type="checkbox"]');
        await selectAllInput.setChecked();

        const deleteVariantsButton = wrapper.find('.sw-product-variants-overview__bulk-delete-action');
        expect(deleteVariantsButton.exists()).toBeTruthy();

        await deleteVariantsButton.trigger('click');

        const deleteModal = wrapper.find('.sw-product-variants-overview__delete-modal');

        expect(deleteModal.exists()).toBeTruthy();
        expect(wrapper.find('.sw-product-variants-overview__modal--confirm-delete-text').text())
            .toBe('sw-product.variations.generatedListDeleteModalMessagePlural');
    });

    it('should not be able to delete variants', async () => {
        global.activeAclRoles = ['product.editor'];

        const wrapper = await createWrapper();

        const deleteVariantsButton = wrapper.find('.sw-product-variants-overview__bulk-delete-action');
        expect(deleteVariantsButton.exists()).toBeFalsy();
    });

    it('should get list will return a list of products', async () => {
        const wrapper = await createWrapper({}, {
            create: () => ({
                search: () => Promise.resolve([
                    {
                        id: '1',
                        name: 'Example product'
                    }
                ])
            })
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.variants).toEqual([{ id: '1', name: 'Example product' }]);
    });

    it('should add the downloads column when the product state is equal "is-download"', async () => {
        const wrapper = await createWrapper({
            productStates: ['is-download']
        }, {
            create: (entity) => {
                if (entity === 'media_default_folder') {
                    return { search: () => Promise.resolve([
                        {
                            id: 'defaultMediaFolderId',
                            entity: 'product_download'
                        }
                    ]) };
                }
                return { search: () => Promise.resolve() };
            }
        });

        expect(wrapper.find('.sw-data-grid__cell--downloads').exists()).toBeTruthy();
    });

    it('should remove file from digital variant item', async () => {
        const item =
            {
                id: '1',
                productNumber: '1',
                name: 'Example product',
                downloads: [
                    {
                        media: {
                            fileName: 'example',
                            fileExtension: 'png',
                        }
                    },
                    {
                        media: {
                            fileName: 'test',
                            fileExtension: 'gif',
                        }
                    }
                ]
            };

        const wrapper = await createWrapper({ productStates: ['is-download'] }, {
            create: () => ({
                search: () => Promise.resolve([item]),
                save: () => Promise.resolve()
            })
        });
        await wrapper.vm.getList();

        // should be deleted
        await wrapper.vm.removeFile('example.png', wrapper.vm.variants.at(0));
        // should not be deleted (because it's the last one)
        await wrapper.vm.removeFile('test.gif', wrapper.vm.variants.at(0));

        const previewItems = wrapper.find('.sw-data-grid__cell--downloads').findAll('.sw-media-compact-upload-v2__preview-item');
        expect(previewItems).toHaveLength(1);
        expect(previewItems.at(0).find('.sw-context-menu-item').text()).toEqual('test.gif');
    });

    it('should save successful uploaded files', async () => {
        const item =
            {
                id: '1',
                productNumber: '1',
                name: 'Example product',
                downloads: [
                    {
                        media: {
                            id: 'lel',
                            fileName: 'test',
                            fileExtension: 'png',
                        }
                    },
                ]
            };

        const file = {
            id: 'test-id',
            fileName: 'example',
            fileExtension: 'png',
        };

        const wrapper = await createWrapper({ productStates: ['is-download'] }, {
            create: () => ({
                search: () => Promise.resolve([item]),
                save: () => Promise.resolve(),
                create: () => Promise.resolve(),
                get: () => Promise.resolve(file)
            })
        });
        await wrapper.vm.getList();

        // not existing
        await wrapper.vm.successfulUpload({ targetId: 'test-id', downloads: [] }, item);
        // existing
        await wrapper.vm.successfulUpload({ targetId: 'test-id', downloads: [] }, item);

        const previewItems = wrapper.find('.sw-data-grid__cell--downloads').findAll('.sw-media-compact-upload-v2__preview-item');
        expect(previewItems).toHaveLength(2);
        expect(previewItems.at(1).find('.sw-context-menu-item').text()).toEqual('example.png');
    });
});
