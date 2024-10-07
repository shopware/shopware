/**
 * @package buyers-experience
 */
import './cmsDataResolver.service';

function createPageMock(debugProperty = 'associations') {
    return {
        name: 'TEST Page',
        type: 'page',
        id: '02218e0fb5e344bcbc6b89ce47bf7e7f',
        sections: [
            {
                position: 0,
                id: '60d3e4dbb4a24984989d2525476c49ab',
                name: 'nice-section',
                visibility: {
                    desktop: false,
                },
                blocks: [
                    {
                        position: 0,
                        type: 'text',
                        id: 'a49b117588f247969f00e2585492ab0d',
                        name: 'nice-block',
                        slots: [
                            {
                                id: 'slot-id-1',
                                versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
                                type: 'text',
                                slot: 'content',
                                translated: {
                                    config: {
                                        content: {
                                            value: '<p>TEST</p>',
                                            source: 'static',
                                        },
                                        verticalAlign: {
                                            value: null,
                                            source: 'static',
                                        },
                                        products: {
                                            value: null,
                                            source: 'static',
                                            entity: {
                                                name: 'myProduct',
                                                criteria: {},
                                            },
                                        },
                                    },
                                },
                                config: {
                                    content: {
                                        value: '<p>TEST</p>',
                                        source: 'static',
                                    },
                                    verticalAlign: {
                                        value: null,
                                        source: 'static',
                                    },
                                    products: {
                                        value: null,
                                        source: 'static',
                                        entity: {
                                            name: 'myProduct',
                                            criteria: {},
                                        },
                                    },
                                },
                                data: null,
                            },
                            {
                                id: 'slot-id-2',
                                versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
                                type: 'text',
                                slot: 'content',
                                translated: {
                                    config: {
                                        content: {
                                            value: '<p>TEST</p>',
                                            source: 'static',
                                        },
                                        verticalAlign: {
                                            value: null,
                                            source: 'static',
                                        },
                                        categories: {
                                            value: null,
                                            source: 'static',
                                            entity: {
                                                name: 'myCategory',
                                                criteria: {
                                                    field: 'name',
                                                    type: 'equals',
                                                    value: 'Test',
                                                },
                                                debugProperty,
                                            },
                                        },
                                    },
                                },
                                config: {
                                    content: {
                                        value: '<p>TEST</p>',
                                        source: 'static',
                                    },
                                    verticalAlign: {
                                        value: null,
                                        source: 'static',
                                    },
                                    categories: {
                                        value: null,
                                        source: 'static',
                                        entity: {
                                            name: 'myCategory',
                                            criteria: {
                                                field: 'name',
                                                type: 'equals',
                                                value: 'Test',
                                            },
                                            debugProperty,
                                        },
                                    },
                                },
                                data: null,
                            },
                            {
                                id: 'slot-id-3',
                                versionId: '0fa91ce3e96a4bc2be4bd9ce752c3421',
                                type: 'invalid',
                            },
                        ],
                    },
                    {
                        position: 1,
                        type: 'text',
                        id: 'a49b117588f247969f00e2585492ab0d',
                        name: 'nice-block',
                        slots: [],
                    },
                ],
            },
        ],
    };
}

const invalidSlotsMock = [
    {
        id: 'slot-id-10',
        versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
        type: 'text',
        slot: 'content',
        translated: {
            config: {
                content: {
                    value: '<p>TEST</p>',
                    source: 'static',
                },
                verticalAlign: {
                    value: null,
                    source: 'static',
                },
                invalid: {
                    value: null,
                    source: 'static',
                    entity: {
                        name: 'myInvalid',
                        criteria: {
                            field: 'name',
                            type: 'equals',
                            value: 'Test',
                        },
                    },
                },
            },
        },
        config: {
            content: {
                value: '<p>TEST</p>',
                source: 'static',
            },
            verticalAlign: {
                value: null,
                source: 'static',
            },
            invalid: {
                value: null,
                source: 'static',
                entity: {
                    name: 'myInvalid',
                    criteria: {
                        field: 'name',
                        type: 'equals',
                        value: 'Test',
                    },
                },
            },
        },
        data: null,
    },
    {
        id: 'slot-id-12',
        versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
        type: 'text',
        slot: 'content',
        translated: {
            config: {
                content: {
                    value: '<p>TEST</p>',
                    source: 'static',
                },
                verticalAlign: {
                    value: null,
                    source: 'static',
                },
                invalidNoCriteria: {
                    value: null,
                    source: 'static',
                    entity: {
                        name: 'myInvalidNoCriteria',
                        criteria: {
                            field: 'name',
                            type: 'equals',
                            value: 'Test',
                        },
                    },
                },
            },
        },
        config: {
            content: {
                value: '<p>TEST</p>',
                source: 'static',
            },
            verticalAlign: {
                value: null,
                source: 'static',
            },
            invalidNoCriteria: {
                value: null,
                source: 'static',
                entity: {
                    name: 'myInvalidNoCriteria',
                    criteria: {
                        field: 'name',
                        type: 'equals',
                        value: 'Test',
                    },
                },
            },
        },
        data: null,
    },
];

const cmsElements = {
    text: {
        collect(slot) {
            if (slot.config.products?.entity) {
                return {
                    'entity-products': {
                        value: [
                            '0190e3b777b078d4a4097dff345ec692',
                        ],
                        key: 'products',
                        name: 'product',
                    },
                };
            }

            const Criteria = Shopware.Data.Criteria;
            if (slot.config.categories?.entity) {
                const debugProperty = slot.config.categories.entity?.debugProperty || 'associations';
                const searchCriteria = new Criteria();

                switch (debugProperty) {
                    case 'associations':
                        searchCriteria.addAssociation('product');
                        break;
                    case 'filters':
                        searchCriteria.addFilter(Criteria.equals('name', 'mock-data'));
                        break;
                    case 'sortings':
                        searchCriteria.addSorting(Criteria.sort('name', 'ASC'));
                        break;
                    case 'term':
                        searchCriteria.setTerm('mock-data');
                        break;
                    default:
                        break;
                }

                return {
                    'entity-categories': {
                        value: [
                            '0190e3b777b078d4a4097dff345ec692',
                        ],
                        key: 'categories',
                        name: 'category',
                        searchCriteria,
                    },
                };
            }

            if (slot.config.invalid?.entity || slot.config.invalidNoCriteria?.entity) {
                const searchCriteria = new Criteria();
                searchCriteria.setTerm('some-search-term');

                return {
                    'entity-invalid': {
                        value: [
                            '0190e3b777b078d4a4097dff345ec692',
                        ],
                        key: 'invalid',
                        name: 'invalid',
                        searchCriteria: slot.config.invalidNoCriteria?.entity ? undefined : searchCriteria,
                    },
                };
            }

            return {};
        },
        enrich(slot, slotEntities) {
            if (!Array.isArray(cmsElements.enrichAssertHelper)) {
                cmsElements.enrichAssertHelper = [];
            }
            cmsElements.enrichAssertHelper.push({ slot, slotEntities });
        },
    },
};

Shopware.Service().register('cmsService', () => {
    return {
        getCmsElementRegistry() {
            return cmsElements;
        },
    };
});

const responses = global.repositoryFactoryMock.responses;
responses.addResponse({
    method: 'Post',
    url: '/search/product',
    status: 200,
    response: {
        data: [
            {
                attributes: {
                    id: 'p.a',
                    name: 'Product A',
                },
                id: 'p.a',
                relationships: [],
            },
            {
                attributes: {
                    id: 'p.b',
                    name: 'Product B',
                },
                id: 'p.b',
                relationships: [],
            },
        ],
        meta: {
            total: 2,
        },
    },
});
responses.addResponse({
    method: 'Post',
    url: '/search/category',
    status: 200,
    response: {
        data: [
            {
                attributes: {
                    id: 'p.a',
                    name: 'Category A',
                },
                id: 'p.a',
                relationships: [],
            },
        ],
        meta: {
            total: 1,
        },
    },
});

function getService() {
    return Shopware.Service().get('cmsDataResolverService');
}

let service;
describe('module/sw-cms/service/cmsDataResolver.service.js', () => {
    beforeEach(async () => {
        service = getService();
        cmsElements.enrichAssertHelper = undefined;
    });

    it('should add visibility settings to sections', async () => {
        const pageMock = createPageMock();
        await service.resolve(pageMock);

        const sections = pageMock.sections;
        expect(sections).toHaveLength(1);
        expect(sections[0].visibility).toEqual({
            desktop: false,
            tablet: true,
            mobile: true,
        });
    });

    it('should add visibility settings to blocks', async () => {
        const pageMock = createPageMock();
        await service.resolve(pageMock);

        const sections = pageMock.sections;
        expect(sections).toHaveLength(1);

        const blocks = sections[0].blocks;
        expect(blocks).toHaveLength(2);
        expect(blocks[0].visibility).toEqual({
            desktop: true,
            tablet: true,
            mobile: true,
        });
    });

    it('should enrich cmsElements if entities are attached', async () => {
        const pageMock = createPageMock();
        const success = await service.resolve(pageMock);
        expect(success).toBe(true);

        expect(cmsElements.enrichAssertHelper).toHaveLength(2);
        expect(Object.keys(cmsElements.enrichAssertHelper[0].slotEntities)).toContain('entity-products');
    });

    const canBeMergedDataProvider = [
        'no additions',
        'associations',
        'filters',
        'sortings',
        'term',
    ];
    it.each(canBeMergedDataProvider)('should not be merged if %s are set, but are still available', async (propertyName) => {
        const pageMock = createPageMock(propertyName);

        const success = await service.resolve(pageMock);
        expect(success).toBe(true);

        expect(cmsElements.enrichAssertHelper).toHaveLength(2);
        expect(Object.keys(cmsElements.enrichAssertHelper[1].slotEntities)).toContain('entity-categories');
    });

    it('should not throw errors, when repositories are not available', async () => {
        const pageMock = createPageMock();

        pageMock.sections[0].blocks[0].slots.push(...invalidSlotsMock);

        const success = await service.resolve(pageMock);
        expect(success).toBe(true);
        expect(cmsElements.enrichAssertHelper).toHaveLength(4);
    });
});
