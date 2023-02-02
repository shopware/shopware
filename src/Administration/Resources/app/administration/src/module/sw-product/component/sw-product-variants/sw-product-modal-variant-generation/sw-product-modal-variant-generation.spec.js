import { shallowMount, createLocalVue } from '@vue/test-utils';
import swModalVariantGeneration from 'src/module/sw-product/component/sw-product-variants/sw-product-modal-variant-generation';
import 'src/app/component/base/sw-modal';
import EntityCollection from 'src/core/data/entity-collection.data';

Shopware.Component.register('sw-product-modal-variant-generation', swModalVariantGeneration);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-product-modal-variant-generation'), {
        localVue,
        propsData: {
            groups: [
                {
                    name: 'Test',
                    description: null,
                    displayType: 'text',
                    sortingType: 'alphanumeric',
                    filterable: true,
                    visibleOnProductDetailPage: true,
                    position: 1,
                    customFields: null,
                    createdAt: '2022-09-26T06:32:09.586+00:00',
                    updatedAt: null,
                    translated: {
                        name: 'Test',
                        description: null,
                        position: 1,
                        customFields: {},
                    },
                    apiAlias: null,
                    id: 'a63105d31de248c09726b0ad32cd5d15',
                    options: [],
                    translations: [],
                },
            ],
            selectedGroups: [
                {
                    name: 'Test',
                    description: null,
                    displayType: 'text',
                    sortingType: 'alphanumeric',
                    filterable: true,
                    visibleOnProductDetailPage: true,
                    position: 1,
                    customFields: null,
                    createdAt: '2022-09-26T06:32:09.586+00:00',
                    updatedAt: null,
                    translated: {
                        name: 'Test',
                        description: null,
                        position: 1,
                        customFields: {},
                    },
                    apiAlias: null,
                    id: 'a63105d31de248c09726b0ad32cd5d15',
                    options: [],
                    translations: [],
                },
            ],
            product: {
                configuratorSettings: new EntityCollection(
                    'product-configurator-settings',
                    '/product-configurator-settings',
                    Shopware.Context.api,
                    null,
                    [
                        {
                            versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
                            productId: 'e8751848318b4564a4c48bd2bba570b2',
                            productVersionId: null,
                            mediaId: null,
                            optionId: 'e10fed21a07149958427cb5339ee4c31',
                            creationState: 'is-download',
                            price: null,
                            position: 0,
                            customFields: null,
                            createdAt: '2022-09-26T06:33:59.508+00:00',
                            updatedAt: null,
                            apiAlias: null,
                            id: '529991749890466e9ff44982bff96305',
                            option: {
                                groupId: 'a63105d31de248c09726b0ad32cd5d15',
                                name: 'Tower',
                                position: 1,
                                colorHexCode: null,
                                mediaId: null,
                                customFields: null,
                                createdAt: '2022-09-26T06:32:18.221+00:00',
                                updatedAt: '2022-09-26T06:33:59.512+00:00',
                                translated: { name: 'Tower', position: 1, customFields: {} },
                                apiAlias: null,
                                id: 'e10fed21a07149958427cb5339ee4c31',
                                translations: [],
                                productConfiguratorSettings: [],
                                productProperties: [],
                                productOptions: [],
                            },
                        },
                        {
                            versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
                            productId: 'e8751848318b4564a4c48bd2bba570b2',
                            productVersionId: null,
                            mediaId: null,
                            optionId: 'd6e90b99fe4842d487b53b59e50491a4',
                            creationState: 'is-physical',
                            price: null,
                            position: 0,
                            customFields: null,
                            createdAt: '2022-09-26T06:32:43.994+00:00',
                            updatedAt: null,
                            apiAlias: null,
                            id: '12bbe30fa2ef4f1d83d0899db1c6d450',
                            option: {
                                groupId: 'a63105d31de248c09726b0ad32cd5d15',
                                name: 'HQ',
                                position: 1,
                                colorHexCode: null,
                                mediaId: null,
                                customFields: null,
                                createdAt: '2022-09-26T06:32:22.274+00:00',
                                updatedAt: null,
                                translated: { name: 'HQ', position: 1, customFields: {} },
                                apiAlias: null,
                                id: 'd6e90b99fe4842d487b53b59e50491a4',
                                translations: [],
                                productConfiguratorSettings: [],
                                productProperties: [],
                                productOptions: [],
                            },
                        },
                        {
                            versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
                            productId: 'e8751848318b4564a4c48bd2bba570b2',
                            productVersionId: null,
                            mediaId: null,
                            optionId: 'd6e90b99fe4842d487b53b59e50491a3',
                            creationState: 'is-physical',
                            price: null,
                            position: 0,
                            customFields: null,
                            createdAt: '2022-09-26T06:32:43.994+00:00',
                            updatedAt: null,
                            apiAlias: null,
                            id: '12bbe30fa2ef4f1d83d0899db1c6d451',
                            option: {
                                groupId: 'a63105d31de248c09726b0ad32cd5d14',
                                name: 'Material',
                                position: 1,
                                colorHexCode: null,
                                mediaId: null,
                                customFields: null,
                                createdAt: '2022-09-26T06:32:22.274+00:00',
                                updatedAt: null,
                                translated: { name: 'Material', position: 1, customFields: {} },
                                apiAlias: null,
                                id: 'd6e90b99fe4842d487b53b59e50491a3',
                                translations: [],
                                productConfiguratorSettings: [],
                                productProperties: [],
                                productOptions: [],
                            },
                        },
                    ],
                ),
            }
        },
        stubs: {
            'sw-tabs': true,
            'sw-tabs-item': true,
            'sw-button': true,
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-product-variants-configurator-selection': true,
            'sw-icon': true,
            'sw-progress-bar': true,
            'sw-alert': true,
            'sw-upload-listener': true,
            'sw-media-compact-upload-v2': true,
            'sw-switch-field': true,
            'sw-data-grid': true,
            'sw-card-filter': true,
        },
        provide: {
            shortcutService: {
                startEventListener() {},
                stopEventListener() {}
            },
            searchRankingService: {
                getSearchFieldsByEntity() {
                    return Promise.resolve(null);
                },
                buildSearchQueriesForEntity: () => {
                    return null;
                },
            },
        }
    });
}

describe('src/module/sw-product/component/sw-product-variants/sw-product-modal-variant-generation', () => {
    beforeAll(() => {
        Shopware.Service().register('syncService', () => {
            return {
                httpClient: {
                    get() {
                        return Promise.resolve({ data: [] });
                    }
                },
                getBasicHeaders() {
                    return {};
                },
                sync() {
                    return Promise.resolve();
                }
            };
        });

        Shopware.State.registerModule('swProductDetail', {
            namespaced: true
        });
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should remove file for all variants', async () => {
        const file = {
            fileName: 'example',
            fileExtension: 'jpg'
        };

        const wrapper = await createWrapper();
        await wrapper.setData({
            usageOfFiles: {
                'example.jpg': [
                    'test-id-1',
                    'test-id-2',
                ],
            },

            idToIndex: {
                'test-id-1': 0,
                'test-id-2': 1,
            },

            variantGenerationQueue: {
                createQueue: [
                    {
                        downloads: [file]
                    },
                    {
                        downloads: [file]
                    }
                ]
            },
            downloadFilesForAllVariants: [
                {
                    id: 'random-id'
                }
            ]
        });

        wrapper.vm.removeFileForAllVariants({
            id: 'random-id',
            fileName: 'example',
            fileExtension: 'jpg'
        });

        expect(wrapper.vm.downloadFilesForAllVariants).toEqual([]);
        expect(wrapper.vm.variantGenerationQueue.createQueue).toEqual([
            {
                downloads: []
            },
            {
                downloads: []
            }
        ]);
    });

    it('should calculate the amount of variants', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            product: {
                configuratorSettings: [
                    {
                        option: { id: '1', groupId: '1' }
                    },
                    {
                        option: { id: '2', groupId: '1' }
                    },
                    {
                        option: { id: '3', groupId: '2' }
                    },
                    {
                        option: { id: '4', groupId: '2' }
                    },
                ]
            }
        });

        wrapper.vm.calcVariantsNumber();

        expect(wrapper.vm.variantsNumber).toBe(4);
    });

    it('should return an empty array if variantGenerationQueue is empty', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            variantGenerationQueue: { deleteQueue: [], createQueue: [], },
        });

        wrapper.vm.getList();

        expect(wrapper.vm.paginatedVariantArray).toStrictEqual([]);
    });

    it('should paginate the variants', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            limit: 2,
            variantGenerationQueue: {
                createQueue: [
                    {
                        id: '1',
                        options: [{
                            entity: {
                                name: 'Book type'
                            }
                        }]
                    },
                    {
                        id: '2',
                        options: [{
                            entity: {
                                name: 'Book type'
                            }
                        }]
                    },
                    {
                        id: '3',
                        options: [{
                            entity: {
                                name: 'Book type'
                            }
                        }],
                    },
                    {
                        id: '4',
                        options: [{
                            entity: {
                                name: 'Book type'
                            }
                        }]
                    },
                ]
            }
        });

        wrapper.vm.handlePageChange({
            page: 1,
            limit: 2
        });

        expect(wrapper.vm.paginatedVariantArray).toEqual([
            {
                id: '1',
                options: [{
                    entity: {
                        name: 'Book type'
                    }
                }]
            },
            {
                id: '2',
                options: [{
                    entity: {
                        name: 'Book type'
                    }
                }]
            },
        ]);

        wrapper.vm.handlePageChange({
            page: 2,
            limit: 2
        });

        expect(wrapper.vm.paginatedVariantArray).toEqual([
            {
                id: '3',
                options: [{
                    entity: {
                        name: 'Book type'
                    }
                }]
            },
            {
                id: '4',
                options: [{
                    entity: {
                        name: 'Book type'
                    }
                }]
            },
        ]);
    });

    it('should filter the variants when searching for them', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            limit: 2,
            variantGenerationQueue: {
                createQueue: [
                    {
                        id: '1',
                        options: [{
                            entity: {
                                name: 'lel'
                            }
                        }]
                    },
                    {
                        id: '2',
                        options: [{
                            entity: {
                                name: 'Book type'
                            }
                        }]
                    },
                ]
            }
        });

        wrapper.vm.onTermChange('lel');

        expect(wrapper.vm.paginatedVariantArray).toEqual([
            {
                id: '1',
                options: [{
                    entity: {
                        name: 'lel'
                    }
                }]
            },
        ]);
    });

    it('should add uploaded all files to variants', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            variantGenerationQueue: {
                createQueue: [
                    {
                        id: 'random-id',
                        productStates: ['is-download'],
                        downloads: []
                    }
                ]
            }
        });
        wrapper.vm.mediaRepository.get = jest.fn().mockReturnValueOnce(
            Promise.resolve({
                id: 'random-id',
                fileName: 'example',
                fileExtension: 'jpg'
            })
        );

        await wrapper.vm.successfulUpload({
            targetId: 'random-id'
        });

        expect(wrapper.vm.downloadFilesForAllVariants).toEqual([{
            id: 'random-id',
            fileName: 'example',
            fileExtension: 'jpg'
        }]);
        expect(wrapper.vm.variantGenerationQueue.createQueue).toEqual([
            {
                id: 'random-id',
                productStates: ['is-download'],
                downloads: [{
                    id: 'random-id',
                    fileName: 'example',
                    fileExtension: 'jpg',
                }]
            }
        ]);
    });

    it('should add the uploaded item', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.mediaRepository.get = jest.fn().mockResolvedValueOnce({ id: 'random-id', fileName: 'example', fileExtension: 'jpg' });

        const item = {
            downloads: []
        };

        await wrapper.vm.successfulUpload({
            targetId: 'random-id'
        }, item);

        expect(item).toStrictEqual({
            downloads: [
                { id: 'random-id', fileName: 'example', fileExtension: 'jpg' }
            ]
        });
    });

    it('generate button should be enabled when every variant has downloads', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            variantGenerationQueue: {
                createQueue: [
                    {
                        id: 'random-id',
                        productStates: ['is-download'],
                        downloads: [{
                            id: 'example-id',
                            fileName: 'example',
                            fileExtension: 'jpg'
                        }]
                    }
                ]
            }
        });
    });

    it('generate button should be disabled when not every variant has downloadable files', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            variantGenerationQueue: {
                createQueue: [
                    {
                        id: 'random-id',
                        productStates: ['is-download'],
                        downloads: []
                    }
                ]
            }
        });

        expect(wrapper.vm.isGenerateButtonDisabled).toBe(true);
    });

    it('should generate digital variants', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.productRepository.save = jest.fn().mockReturnValueOnce(Promise.resolve({}));

        await wrapper.setData({
            variantGenerationQueue: {
                createQueue: [
                    {
                        id: 'random-id',
                        downloads: [{
                            id: 'random-id'
                        }],
                        productStates: ['is-download'],
                    }
                ],
                deleteQueue: [{
                    id: 'delete-id'
                }]
            }
        });

        await wrapper.vm.generateVariants();
        await flushPromises();

        // event should only be called once
        expect(wrapper.emitted('variations-finish-generate')).toHaveLength(1);

        // should generate digital variants with max purchase 1
        expect(wrapper.vm.variantGenerationQueue.createQueue[0].maxPurchase).toBe(1);
    });

    it('should generate variants', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.productRepository.save = jest.fn().mockReturnValueOnce(Promise.resolve({}));

        await wrapper.setData({
            variantGenerationQueue: {
                createQueue: [
                    {
                        id: 'random-id',
                        downloads: [{
                            id: 'random-id'
                        }],
                        productStates: ['is-download'],
                    }
                ],
                deleteQueue: [{
                    id: 'delete-id'
                }]
            },
            variantsGenerator: {
                generateVariants: () => Promise.resolve()
            }
        });

        await wrapper.vm.generateVariants();
        await flushPromises();

        expect(wrapper.emitted('variations-finish-generate')).toHaveLength(1);
    });

    it('should show variant generation step', async () => {
        const configuratorSetting = new EntityCollection(
            'property-group-option',
            '/property-group-option',
            Shopware.Context.api,
            null,
            [
                {
                    id: 'd6e90b99fe4842d487b53b59e50491a4',
                    name: 'Option 1',
                    group: {
                        id: '8badf7ebe678ab968fe88c269c214ea6',
                        name: 'Group 1',
                    },
                    option: {
                        id: 'd6e90b99fe4842d487b53b59e50491a4',
                        groupId: '8badf7ebe678ab968fe88c269c214ea6',
                    },
                },
                {
                    id: 'd6e90b99fe4842d487b53b59e50491a3',
                    name: 'Option 2',
                    group: {
                        id: 'a63105d31de248c09726b0ad32cd5d14',
                        name: 'Group 2',
                    },
                    option: {
                        id: 'd6e90b99fe4842d487b53b59e50491a3',
                        groupId: 'a63105d31de248c09726b0ad32cd5d14',
                    },
                },
                {
                    id: 'd6e90b99fe4842d487b53b59e50491a5',
                    name: 'Option 3',
                    group: {
                        id: 'a63105d31de248c09726b0ad32cd5d15',
                        name: 'Group 3',
                    },
                    option: {
                        id: 'd6e90b99fe4842d487b53b59e50491a5',
                        groupId: 'a63105d31de248c09726b0ad32cd5d15',
                    },
                },
            ],
        );

        const wrapper = await createWrapper();
        wrapper.vm.product.configuratorSettings = configuratorSetting;
        wrapper.vm.productRepository.save = jest.fn().mockReturnValueOnce(Promise.resolve({}));

        wrapper.vm.optionRepository.search = jest.fn().mockReturnValueOnce(Promise.resolve(configuratorSetting));

        wrapper.vm.variantsGenerator.loadExisting = jest.fn().mockReturnValueOnce(Promise.resolve([]));

        const responses = global.repositoryFactoryMock.responses;

        responses.addResponse({
            method: 'Post',
            url: '/search/property-group-option',
            status: 200,
            response: {
                data: [
                    {
                        id: 'd6e90b99fe4842d487b53b59e50491a4',
                        attributes: {
                            id: 'd6e90b99fe4842d487b53b59e50491a4',
                            name: 'Foobar option 1',
                            group: {
                                id: '8badf7ebe678ab968fe88c269c214ea6',
                                name: 'Foobar group 1',
                            },
                        },
                        relationships: [],
                    },
                    {
                        id: 'd6e90b99fe4842d487b53b59e50491a3',
                        attributes: {
                            id: 'd6e90b99fe4842d487b53b59e50491a3',
                            name: 'Foobar option 2',
                            group: {
                                id: 'a63105d31de248c09726b0ad32cd5d14',
                                name: 'Foobar group 2',
                            },
                        },
                        relationships: [],
                    },
                    {
                        id: 'd6e90b99fe4842d487b53b59e50491a5',
                        attributes: {
                            id: 'd6e90b99fe4842d487b53b59e50491a5',
                            name: 'Foobar option 3',
                            group: {
                                id: 'a63105d31de248c09726b0ad32cd5d15',
                                name: 'Foobar group 3',
                            },
                        },
                        relationships: [],
                    }
                ]
            }
        });

        await wrapper.vm.showNextStep();
        await flushPromises();

        expect(wrapper.vm.variantGenerationQueue.createQueue).toHaveLength(1);
        expect(wrapper.vm.variantGenerationQueue.createQueue[0].options).toHaveLength(3);
    });

    it('should show variant generation step without any to create', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.productRepository.save = jest.fn().mockReturnValueOnce(Promise.resolve({}));
        wrapper.vm.variantsGenerator.filterVariations = jest.fn().mockReturnValueOnce(Promise.resolve({
            deleteQueue: [], createQueue: []
        }));

        await wrapper.vm.showNextStep();
        await flushPromises();

        expect(wrapper.vm.variantGenerationQueue.createQueue).toHaveLength(0);
        expect(wrapper.vm.variantGenerationQueue.deleteQueue).toHaveLength(0);
    });

    it('should prevent uploads of duplicate files on single variants', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.mediaRepository.get = jest.fn().mockResolvedValueOnce({ id: 'random-id', fileName: 'example', fileExtension: 'jpg' });

        const item = {
            downloads: [{ id: 'random-id', fileName: 'example', fileExtension: 'jpg' }]
        };

        await wrapper.vm.successfulUpload({
            targetId: 'random-id'
        }, item);

        expect(item).toStrictEqual({
            downloads: [
                { id: 'random-id', fileName: 'example', fileExtension: 'jpg' }
            ]
        });
    });

    it('should prevent uploads of duplicates files on all variants', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            downloadFilesForAllVariants: [
                {
                    id: 'random-id',
                    fileName: 'example',
                    fileExtension: 'jpg'
                }
            ]
        });
        wrapper.vm.mediaRepository.get = jest.fn().mockReturnValueOnce(
            Promise.resolve({
                id: 'random-id',
                fileName: 'example',
                fileExtension: 'jpg'
            })
        );

        await wrapper.vm.successfulUpload({
            targetId: 'random-id'
        });

        expect(wrapper.vm.downloadFilesForAllVariants).toEqual([{
            id: 'random-id',
            fileName: 'example',
            fileExtension: 'jpg'
        }]);
    });

    it('should only make visible variants digital by using "make all variants digital"', async () => {
        const items = [
            {
                id: '1',
                options: [{
                    entity: {
                        name: 'test'
                    }
                }],
                downloads: [],
                productStates: []
            },
            {
                id: '2',
                options: [{
                    entity: {
                        name: 'lel'
                    }
                }],
                downloads: [],
                productStates: ['is-download']
            }
        ];

        const wrapper = await createWrapper();
        await wrapper.setData({
            variantGenerationQueue: {
                createQueue: items
            },
        });

        wrapper.vm.onTermChange('lel');
        wrapper.vm.onChangeAllVariantValues(false);
        wrapper.vm.onTermChange('test');
        wrapper.vm.onChangeAllVariantValues(true);
        wrapper.vm.onTermChange('');

        items[0].productStates = ['is-download'];
        items[1].productStates = [];
        expect(wrapper.vm.paginatedVariantArray).toEqual(items);
    });

    it('should only add uploaded file to visible variants by using "upload to all variants"', async () => {
        const items = [
            {
                id: '1',
                options: [{
                    entity: {
                        name: 'test'
                    }
                }],
                downloads: [],
                productStates: ['is-download']
            },
            {
                id: '2',
                options: [{
                    entity: {
                        name: 'lel'
                    }
                }],
                downloads: [],
                productStates: ['is-download']
            }
        ];
        const file = {
            id: 'random-id',
            fileName: 'example',
            fileExtension: 'jpg'
        };

        const wrapper = await createWrapper();
        await wrapper.setData({
            variantGenerationQueue: {
                createQueue: items
            },
            downloadFilesForAllVariants: [file],
        });
        wrapper.vm.mediaRepository.get = jest.fn().mockReturnValueOnce(
            Promise.resolve(file)
        );

        wrapper.vm.onTermChange('test');
        await wrapper.vm.successfulUpload({
            targetId: 'random-id'
        });
        wrapper.vm.onTermChange('');

        items[0].downloads = [file];
        expect(wrapper.vm.paginatedVariantArray).toEqual(items);
    });
});
