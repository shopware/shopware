import BulkEditApiFactory from 'src/module/sw-bulk-edit/service/bulk-edit.api.factory';
import BulkEditProductHandler from 'src/module/sw-bulk-edit/service/handler/bulk-edit-product.handler';

const EntityDefinitionFactory = require('src/core/factory/entity-definition.factory').default;

function getBulkEditApiFactory() {
    return new BulkEditApiFactory();
}

function getBulkEditProductHandler() {
    const factory = getBulkEditApiFactory();

    const handler = factory.getHandler('product');

    handler.syncService = {
        sync: () => {
            return true;
        }
    };

    return handler;
}

describe('module/sw-bulk-edit/service/handler/bulk-edit-product.handler', () => {
    it('is registered correctly', () => {
        const factory = getBulkEditApiFactory();

        const handler = factory.getHandler('product');

        expect(handler).toBeInstanceOf(BulkEditProductHandler);
        expect(handler.name).toBe('bulkEditProductHandler');
    });

    it('should call buildBulkSyncPayload when using bulkEdit', async () => {
        const handler = getBulkEditProductHandler();

        const bulkEditProductHandler = jest.spyOn(handler, 'buildBulkSyncPayload').mockImplementation(() => Promise.resolve({
            upsert: {
                entity: 'order'
            }
        }));

        const result = await handler.bulkEdit(['abc', 'xyz'], []);

        expect(bulkEditProductHandler).toHaveBeenCalledTimes(1);
        expect(bulkEditProductHandler).toHaveBeenCalledWith([]);
        expect(handler.entityName).toEqual('product');
        expect(handler.entityIds).toEqual(['abc', 'xyz']);
        expect(result).toEqual(true);
    });

    it('should call syncService sync when using bulkEditProductHandler', async () => {
        const handler = getBulkEditProductHandler();
        const payload = { product: { operation: 'upsert', entity: 'product', payload: [] } };

        const buildBulkSyncPayloadMethod = jest.spyOn(handler, 'buildBulkSyncPayload').mockImplementation(() => Promise.resolve(payload));
        const syncMethod = jest.spyOn(handler.syncService, 'sync').mockImplementation(() => Promise.resolve(true));

        const changes = [{ type: 'overwrite', field: 'description', value: 'test' }];

        const result = await handler.bulkEdit([], changes);

        expect(buildBulkSyncPayloadMethod).toHaveBeenCalledTimes(1);
        expect(buildBulkSyncPayloadMethod).toHaveBeenCalledWith(changes);

        expect(syncMethod).toHaveBeenCalledTimes(1);
        expect(syncMethod).toHaveBeenCalledWith(payload, {}, { 'single-operation': 1, 'sw-language-id': Shopware.Context.api.languageId });
        expect(result).toEqual(true);
    });

    describe('test buildBulkSyncPayload', () => {
        let handler = null;

        beforeEach(() => {
            handler = getBulkEditProductHandler();

            handler.groupedPayload = {
                upsert: {},
                delete: {}
            };
            handler.entityName = 'product';
            handler.entityIds = ['product_1', 'product_2'];
        });

        const cases = [
            [
                'empty changes',
                [],
                {}
            ],
            [
                'invalid field',
                [{ type: 'overwrite', field: 'invalid-field', value: 'test' }, {
                    type: 'clear',
                    field: 'invalid-field-2',
                    value: 'test'
                }],
                {}
            ],
            [
                'unsupported type',
                [{ type: 'not-support-type', field: 'description', value: 'test' }],
                {}
            ],
            [
                'overwrite single field',
                [{ type: 'overwrite', field: 'description', value: 'test' }],
                {
                    'upsert-product': {
                        action: 'upsert',
                        entity: 'product',
                        payload: [
                            {
                                id: 'product_1',
                                description: 'test'
                            },
                            {
                                id: 'product_2',
                                description: 'test'
                            }
                        ]
                    }
                }
            ],
            [
                'overwrite custom field',
                [{ type: 'overwrite', field: 'customFields', value: { custom_health_nostrum_facere_quo: 'lorem ipsum' } }],
                {
                    'upsert-product': {
                        action: 'upsert',
                        entity: 'product',
                        payload: [
                            {
                                id: 'product_1',
                                customFields: {
                                    custom_health_nostrum_facere_quo: 'lorem ipsum'
                                }
                            },
                            {
                                id: 'product_2',
                                customFields: {
                                    custom_health_nostrum_facere_quo: 'lorem ipsum'
                                }
                            }
                        ]
                    }
                }
            ],
            [
                'clear single string field',
                [{ type: 'clear', field: 'description' }],
                {
                    'upsert-product': {
                        action: 'upsert',
                        entity: 'product',
                        payload: [
                            {
                                id: 'product_1',
                                description: null
                            },
                            {
                                id: 'product_2',
                                description: null
                            }
                        ]
                    }
                }
            ],
            [
                'clear multiple scalar fields',
                [{ type: 'clear', field: 'description' }, { type: 'clear', field: 'stock' }],
                {
                    'upsert-product': {
                        action: 'upsert',
                        entity: 'product',
                        payload: [
                            {
                                id: 'product_1',
                                description: null,
                                stock: 0
                            },
                            {
                                id: 'product_2',
                                description: null,
                                stock: 0
                            }
                        ]
                    }
                }
            ],
            [
                'overwrite multiple fields',
                [{ type: 'overwrite', field: 'description', value: 'test' }, {
                    type: 'overwrite',
                    field: 'stock',
                    value: 10
                }],
                {
                    'upsert-product': {
                        action: 'upsert',
                        entity: 'product',
                        payload: [
                            {
                                id: 'product_1',
                                description: 'test',
                                stock: 10
                            },
                            {
                                id: 'product_2',
                                description: 'test',
                                stock: 10
                            }
                        ]
                    }
                }
            ],
            [
                'changes with invalid field and unsupported type',
                [{ type: 'overwrite', field: 'description', value: 'test' }, {
                    type: 'overwrite',
                    field: 'invalid-field',
                    value: 10
                }, { type: 'un-support-type', field: 'name', value: 10 }],
                {
                    'upsert-product': {
                        action: 'upsert',
                        entity: 'product',
                        payload: [
                            {
                                id: 'product_1',
                                description: 'test'
                            },
                            {
                                id: 'product_2',
                                description: 'test'
                            }
                        ]
                    }
                }
            ],
            [
                'change association with invalid field',
                [{
                    type: 'overwrite',
                    field: 'invalidField',
                    value: ['category_1', 'category_2']
                }],
                {}
            ],
            [
                'overwrite an association with no duplicated',
                [{
                    type: 'overwrite',
                    field: 'categories',
                    value: [{ id: 'category_1' }, { id: 'category_2' }]
                }],
                {
                    'upsert-product_category': {
                        action: 'upsert',
                        entity: 'product_category',
                        payload: [
                            {
                                productId: 'product_1',
                                categoryId: 'category_1'
                            },
                            {
                                productId: 'product_2',
                                categoryId: 'category_1'
                            },
                            {
                                productId: 'product_1',
                                categoryId: 'category_2'
                            },
                            {
                                productId: 'product_2',
                                categoryId: 'category_2'
                            }
                        ]
                    }
                },
                {
                    product_category: []
                }
            ],
            [
                'overwrite an association with some duplicated',
                [{
                    type: 'overwrite',
                    field: 'categories',
                    value: [{ id: 'category_1' }, { id: 'category_2' }]
                }],
                {
                    'upsert-product_category': {
                        action: 'upsert',
                        entity: 'product_category',
                        payload: [
                            {
                                productId: 'product_2',
                                categoryId: 'category_1'
                            },
                            {
                                productId: 'product_1',
                                categoryId: 'category_2'
                            }
                        ]
                    },
                    'delete-product_category': {
                        action: 'delete',
                        entity: 'product_category',
                        payload: [
                            {
                                productId: 'product_1',
                                categoryId: 'category_3'
                            },
                            {
                                productId: 'product_2',
                                categoryId: 'category_4'
                            }
                        ]
                    }
                },
                {
                    product_category: [
                        {
                            productId: 'product_1',
                            categoryId: 'category_1'
                        },
                        {
                            productId: 'product_2',
                            categoryId: 'category_2'
                        },
                        {
                            productId: 'product_1',
                            categoryId: 'category_3'
                        },
                        {
                            productId: 'product_2',
                            categoryId: 'category_4'
                        }
                    ]
                }
            ],
            [
                'overwrite an oneToMany association',
                [{ type: 'overwrite', field: 'media', mappingReferenceField: 'mediaId', value: [{ mediaId: 'media_1' }, { mediaId: 'media_2' }] }],
                {
                    'upsert-product_media': {
                        action: 'upsert',
                        entity: 'product_media',
                        payload: [
                            {
                                productId: 'product_1',
                                mediaId: 'media_1'
                            },
                            {
                                productId: 'product_2',
                                mediaId: 'media_1'
                            }
                        ]
                    },
                    'delete-product_media': {
                        action: 'delete',
                        entity: 'product_media',
                        payload: [
                            {
                                id: 'product_media_3'
                            }
                        ]
                    }
                },
                {
                    product_media: [
                        {
                            id: 'product_media_1',
                            productId: 'product_1',
                            mediaId: 'media_2'
                        },
                        {
                            id: 'product_media_2',
                            productId: 'product_2',
                            mediaId: 'media_2'
                        },
                        {
                            id: 'product_media_3',
                            productId: 'product_2',
                            mediaId: 'media_3'
                        }
                    ]
                }
            ],
            [
                'overwrite an oneToMany association with extra field',
                [{ type: 'overwrite', field: 'visibilities', mappingReferenceField: 'salesChannelId', value: [{ salesChannelId: 'scn_1', visibility: 20 }, { salesChannelId: 'scn_2', visibility: 30 }] }],
                {
                    'upsert-product_visibility': {
                        action: 'upsert',
                        entity: 'product_visibility',
                        payload: [
                            {
                                productId: 'product_2',
                                salesChannelId: 'scn_1',
                                visibility: 20
                            },
                            {
                                productId: 'product_1',
                                salesChannelId: 'scn_2',
                                visibility: 30
                            },
                            {
                                id: 'product_scn_2',
                                visibility: 30
                            }
                        ]
                    },
                    'delete-product_visibility': {
                        action: 'delete',
                        entity: 'product_visibility',
                        payload: [
                            {
                                id: 'product_scn_3'
                            },
                            {
                                id: 'product_scn_4'
                            }
                        ]
                    }
                },
                {
                    product_visibility: [
                        {
                            id: 'product_scn_1',
                            productId: 'product_1',
                            visibility: 20,
                            salesChannelId: 'scn_1'
                        },
                        {
                            id: 'product_scn_2',
                            productId: 'product_2',
                            visibility: 20,
                            salesChannelId: 'scn_2'
                        },
                        {
                            id: 'product_scn_3',
                            productId: 'product_1',
                            salesChannelId: 'scn_3'
                        },
                        {
                            id: 'product_scn_4',
                            productId: 'product_2',
                            salesChannelId: 'scn_4'
                        }
                    ]
                }
            ],
            [
                'add an oneToMany association',
                [{ type: 'add', field: 'media', mappingReferenceField: 'mediaId', value: [{ mediaId: 'media_1' }, { mediaId: 'media_2' }] }],
                {
                    'upsert-product_media': {
                        action: 'upsert',
                        entity: 'product_media',
                        payload: [
                            {
                                productId: 'product_1',
                                mediaId: 'media_1'
                            },
                            {
                                productId: 'product_2',
                                mediaId: 'media_1'
                            },
                            {
                                productId: 'product_2',
                                mediaId: 'media_2'
                            }
                        ]
                    }
                },
                {
                    product_media: [
                        {
                            id: 'product_media_1',
                            productId: 'product_1',
                            mediaId: 'media_2'
                        }
                    ]
                }
            ],
            [
                'remove an oneToMany association',
                [{ type: 'clear', field: 'media', mappingReferenceField: 'mediaId', value: [{ mediaId: 'media_1' }, { mediaId: 'media_2' }] }],
                {
                    'delete-product_media': {
                        action: 'delete',
                        entity: 'product_media',
                        payload: [
                            {
                                id: 'product_media_1'
                            }
                        ]
                    }
                },
                {
                    product_media: [
                        {
                            id: 'product_media_1',
                            productId: 'product_1',
                            mediaId: 'media_2'
                        }
                    ]
                }
            ],
            [
                'clear an oneToMany association',
                [{ type: 'clear', field: 'media', mappingReferenceField: 'mediaId' }],
                {
                    'delete-product_media': {
                        action: 'delete',
                        entity: 'product_media',
                        payload: [
                            {
                                id: 'product_media_1'
                            },
                            {
                                id: 'product_media_2'
                            }
                        ]
                    }
                },
                {
                    product_media: [
                        {
                            id: 'product_media_1',
                            productId: 'product_1',
                            mediaId: 'media_1'
                        },
                        {
                            id: 'product_media_2',
                            productId: 'product_1',
                            mediaId: 'media_2'
                        }
                    ]
                }
            ],
            [
                'overwrite an association with all duplicated',
                [{
                    type: 'overwrite',
                    field: 'categories',
                    value: [{ id: 'category_1' }, { id: 'category_2' }]
                }],
                {},
                {
                    product_category: [
                        {
                            productId: 'product_1',
                            categoryId: 'category_1'
                        },
                        {
                            productId: 'product_1',
                            categoryId: 'category_2'
                        },
                        {
                            productId: 'product_2',
                            categoryId: 'category_1'
                        },
                        {
                            productId: 'product_2',
                            categoryId: 'category_2'
                        }
                    ]
                }
            ],
            [
                'overwrite an association with duplicated',
                [{
                    type: 'overwrite',
                    field: 'categories',
                    value: [{ id: 'category_1' }, { id: 'category_2' }]
                }],
                {
                    'upsert-product_category': {
                        action: 'upsert',
                        entity: 'product_category',
                        payload: [
                            {
                                productId: 'product_2',
                                categoryId: 'category_1'
                            },
                            {
                                productId: 'product_1',
                                categoryId: 'category_2'
                            }
                        ]
                    }
                },
                {
                    product_category: [
                        {
                            productId: 'product_1',
                            categoryId: 'category_1'
                        },
                        {
                            productId: 'product_2',
                            categoryId: 'category_2'
                        }
                    ]

                }
            ],
            [
                'add an association',
                [{
                    type: 'add',
                    field: 'categories',
                    value: [{ id: 'category_1' }, { id: 'category_2' }, { id: 'category_3' }]
                }],
                {
                    'upsert-product_category': {
                        action: 'upsert',
                        entity: 'product_category',
                        payload: [
                            {
                                productId: 'product_2',
                                categoryId: 'category_1'
                            },
                            {
                                productId: 'product_1',
                                categoryId: 'category_2'
                            },
                            {
                                productId: 'product_2',
                                categoryId: 'category_3'
                            }
                        ]
                    }
                },
                {
                    product_category: [
                        {
                            productId: 'product_1',
                            categoryId: 'category_1'
                        },
                        {
                            productId: 'product_2',
                            categoryId: 'category_2'
                        },
                        {
                            productId: 'product_1',
                            categoryId: 'category_3'
                        },
                        {
                            productId: 'product_2',
                            categoryId: 'category_4'
                        }
                    ]
                }
            ],
            [
                'remove an association',
                [{
                    type: 'remove',
                    field: 'categories',
                    value: [{ id: 'category_1' }, { id: 'category_2' }]
                }],
                {
                    'delete-product_category': {
                        action: 'delete',
                        entity: 'product_category',
                        payload: [
                            {
                                productId: 'product_1',
                                categoryId: 'category_1'
                            },
                            {
                                productId: 'product_2',
                                categoryId: 'category_2'
                            }
                        ]
                    }
                },
                {
                    product_category: [
                        {
                            productId: 'product_1',
                            categoryId: 'category_1'
                        },
                        {
                            productId: 'product_2',
                            categoryId: 'category_2'
                        }
                    ]
                }
            ],
            [
                'all operators at once',
                [
                    { type: 'overwrite', field: 'description', value: 'test' },
                    { type: 'clear', field: 'stock' },
                    { type: 'remove', mappingReferenceField: 'mediaId', field: 'media', value: { mediaId: 'media_1' } },
                    {
                        type: 'add',
                        field: 'categories',
                        value: [{ id: 'category_1' }, { id: 'category_2' }]
                    }
                ],
                {
                    'upsert-product': {
                        action: 'upsert',
                        entity: 'product',
                        payload: [
                            {
                                id: 'product_1',
                                description: 'test',
                                stock: 0
                            },
                            {
                                id: 'product_2',
                                description: 'test',
                                stock: 0
                            }
                        ]
                    },
                    'upsert-product_category': {
                        action: 'upsert',
                        entity: 'product_category',
                        payload: [
                            {
                                productId: 'product_2',
                                categoryId: 'category_1'
                            },
                            {
                                productId: 'product_1',
                                categoryId: 'category_2'
                            }
                        ]
                    },
                    'delete-product_media': {
                        action: 'delete',
                        entity: 'product_media',
                        payload: [
                            {
                                id: 'product_media_1'
                            }
                        ]
                    }
                },
                {
                    product_category: [
                        {
                            productId: 'product_1',
                            categoryId: 'category_1'
                        },
                        {
                            productId: 'product_2',
                            categoryId: 'category_2'
                        },
                        {
                            productId: 'product_1',
                            categoryId: 'category_3'
                        },
                        {
                            productId: 'product_2',
                            categoryId: 'category_4'
                        }
                    ],
                    product_media: [
                        {
                            productId: 'product_2',
                            mediaId: 'media_1',
                            id: 'product_media_1'
                        }
                    ]
                }
            ]
        ];

        it.each(cases)('%s', async (testName, input, output, existAssociations = {}) => {
            const mockEntitySchema = {
                product: {
                    entity: 'product',
                    properties: {
                        id: {
                            type: 'uuid'
                        },
                        price: {
                            type: 'json_object',
                            properties: []
                        },
                        cover: {
                            type: 'association',
                            relation: 'many_to_one',
                            entity: 'product_media'
                        },
                        name: {
                            type: 'string'
                        },
                        description: {
                            type: 'string'
                        },
                        stock: {
                            type: 'int'
                        },
                        customFields: {
                            type: 'json_object'
                        },
                        media: {
                            type: 'association',
                            relation: 'one_to_many',
                            entity: 'product_media',
                            localField: 'id',
                            referenceField: 'productId'
                        },
                        manufacturer: {
                            type: 'association',
                            relation: 'many_to_one',
                            entity: 'product_manufacturer'
                        },
                        translations: {
                            type: 'association',
                            relation: 'one_to_many',
                            entity: 'product_translation'
                        },
                        categories: {
                            type: 'association',
                            relation: 'many_to_many',
                            entity: 'category',
                            flags: {},
                            localField: 'id',
                            referenceField: 'id',
                            mapping: 'product_category',
                            local: 'productId',
                            reference: 'categoryId'
                        },
                        visibilities: {
                            type: 'association',
                            relation: 'one_to_many',
                            entity: 'product_visibility',
                            localField: 'id',
                            referenceField: 'productId'
                        }
                    }
                },
                product_category: {
                    entity: 'product_category',
                    relation: 'many_to_many'
                },
                product_visibility: {
                    entity: 'product_visibility',
                    properties: {
                        id: {
                            type: 'uuid'
                        },
                        productId: {
                            type: 'uuid'
                        },
                        salesChannelId: {
                            type: 'uuid'
                        },
                        visibility: {
                            type: 'int'
                        }
                    }
                },
                product_manufacturer: {
                    entity: 'product_manufacturer',
                    properties: {
                        id: {
                            type: 'uuid'
                        },
                        name: {
                            type: 'string'
                        },
                        media: {
                            type: 'association',
                            relation: 'many_to_one',
                            entity: 'media'
                        },
                        products: {
                            type: 'association',
                            relation: 'one_to_many',
                            entity: 'product'
                        }
                    }
                },
                product_media: {
                    entity: 'product_media',
                    properties: {
                        id: {
                            type: 'uuid'
                        },
                        media: {
                            type: 'association',
                            relation: 'many_to_one',
                            entity: 'media'
                        }
                    }
                },
                media: {
                    entity: 'media',
                    properties: {
                        id: {
                            type: 'uuid'
                        },
                        translations: {
                            type: 'association',
                            relation: 'one_to_many',
                            entity: 'media_translation'
                        }
                    }
                }
            };

            Shopware.EntityDefinition = EntityDefinitionFactory;
            Object.keys(mockEntitySchema).forEach((entity) => {
                Shopware.EntityDefinition.add(entity, mockEntitySchema[entity]);
            });

            const spy = jest.spyOn(console, 'warn').mockImplementation();

            const spyRepository = jest.spyOn(handler.repositoryFactory, 'create').mockImplementation((entity) => {
                return {
                    search: async () => Promise.resolve(existAssociations[entity]),
                    searchIds: async () => Promise.resolve({ data: existAssociations[entity] })
                };
            });

            expect(await handler.buildBulkSyncPayload(input)).toEqual(output);

            spy.mockRestore();

            spyRepository.mockRestore();
        });
    });
});
