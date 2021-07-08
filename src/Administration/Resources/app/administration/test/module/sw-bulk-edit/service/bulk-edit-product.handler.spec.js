import BulkEditApiFactory from 'src/module/sw-bulk-edit/service/bulk-edit.api.factory';
import BulkEditProductHandler from 'src/module/sw-bulk-edit/service/handler/bulk-edit-product.handler';

function getBulkEditApiFactory() {
    const factory = new BulkEditApiFactory();

    return factory;
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

        const bulkEditProductHandler = jest.spyOn(handler, 'buildBulkSyncPayload').mockImplementation(() => Promise.resolve({}));

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
        expect(syncMethod).toHaveBeenCalledWith(payload, {}, { 'single-operation': 1 });
        expect(result).toEqual(true);
    });

    describe('test buildBulkSyncPayload', () => {
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
                'change association with invalid mapping entity',
                [{
                    type: 'overwrite',
                    mappingEntity: 'invalid_mapping_entity',
                    field: 'categoryId',
                    value: ['category_1', 'category_2']
                }],
                {}
            ],
            [
                'change association with invalid mapping entity field',
                [{
                    type: 'overwrite',
                    mappingEntity: 'product_category',
                    field: 'customerId',
                    value: ['category_1', 'category_2']
                }],
                {}
            ],
            [
                'overwrite an association with no duplicated',
                [{
                    type: 'overwrite',
                    mappingEntity: 'product_category',
                    field: 'categoryId',
                    value: ['category_1', 'category_2']
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
                    mappingEntity: 'product_category',
                    field: 'categoryId',
                    value: ['category_1', 'category_2']
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
                            product_id: 'product_1',
                            category_id: 'category_1'
                        },
                        {
                            product_id: 'product_2',
                            category_id: 'category_2'
                        },
                        {
                            product_id: 'product_1',
                            category_id: 'category_3'
                        },
                        {
                            product_id: 'product_2',
                            category_id: 'category_4'
                        }
                    ]
                }
            ],
            [
                'overwrite an oneToMany association',
                [{ type: 'overwrite', mappingEntity: 'product_media', field: 'mediaId', value: ['media_1', 'media_2'] }],
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
                    }
                },
                {
                    product_media: [
                        {
                            productId: 'product_1',
                            mediaId: 'media_2'
                        },
                        {
                            productId: 'product_2',
                            mediaId: 'media_2'
                        }
                    ]
                }
            ],
            [
                'overwrite an association with all duplicated',
                [{
                    type: 'overwrite',
                    mappingEntity: 'product_category',
                    field: 'categoryId',
                    value: ['category_1', 'category_2']
                }],
                {},
                {
                    product_category: [
                        {
                            product_id: 'product_1',
                            category_id: 'category_1'
                        },
                        {
                            product_id: 'product_1',
                            category_id: 'category_2'
                        },
                        {
                            product_id: 'product_2',
                            category_id: 'category_1'
                        },
                        {
                            product_id: 'product_2',
                            category_id: 'category_2'
                        }
                    ]
                }
            ],
            [
                'overwrite an association with duplicated',
                [{
                    type: 'overwrite',
                    mappingEntity: 'product_category',
                    field: 'categoryId',
                    value: ['category_1', 'category_2']
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
                            product_id: 'product_1',
                            category_id: 'category_1'
                        },
                        {
                            product_id: 'product_2',
                            category_id: 'category_2'
                        }
                    ]

                }
            ],
            [
                'add an association',
                [{
                    type: 'add',
                    mappingEntity: 'product_category',
                    field: 'categoryId',
                    value: ['category_1', 'category_2', 'category_3']
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
                            product_id: 'product_1',
                            category_id: 'category_1'
                        },
                        {
                            product_id: 'product_2',
                            category_id: 'category_2'
                        },
                        {
                            product_id: 'product_1',
                            category_id: 'category_3'
                        },
                        {
                            product_id: 'product_2',
                            category_id: 'category_4'
                        }
                    ]
                }
            ],
            [
                'remove an association',
                [{
                    type: 'remove',
                    mappingEntity: 'product_category',
                    field: 'categoryId',
                    value: ['category_1', 'category_2']
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
                            product_id: 'product_1',
                            category_id: 'category_1'
                        },
                        {
                            product_id: 'product_2',
                            category_id: 'category_2'
                        }
                    ]
                }
            ],
            [
                'all operators at once',
                [
                    { type: 'overwrite', field: 'description', value: 'test' },
                    { type: 'clear', field: 'stock' },
                    { type: 'remove', mappingEntity: 'product_media', field: 'mediaId', value: ['media_1'] },
                    {
                        type: 'add',
                        mappingEntity: 'product_category',
                        field: 'categoryId',
                        value: ['category_1', 'category_2']
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
                            product_id: 'product_1',
                            category_id: 'category_1'
                        },
                        {
                            product_id: 'product_2',
                            category_id: 'category_2'
                        },
                        {
                            product_id: 'product_1',
                            category_id: 'category_3'
                        },
                        {
                            product_id: 'product_2',
                            category_id: 'category_4'
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

        const handler = getBulkEditProductHandler();
        handler.entityName = 'product';
        handler.entityIds = ['product_1', 'product_2'];

        it.each(cases)('%s', async (testName, input, output, existAssociations = {}) => {
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
