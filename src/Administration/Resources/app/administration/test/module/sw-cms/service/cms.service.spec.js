import 'src/module/sw-cms/service/cms.service';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import Entity from 'src/core/data/entity.data';

describe('module/sw-cms/service/cms.service.spec.js', () => {
    const cmsService = Shopware.Service('cmsService');

    const mediaEntites1 = [{
        id: '123',
        name: 'media',
        filtered: true,
    }];

    const mediaEntites2 = [{
        id: '567',
        name: 'media',
        filtered: true,
    }];

    const enrichData = {
        'entity-media-0':
            new Shopware.Data.EntityCollection(
                '/media',
                'media',
                null,
                null,
                mediaEntites1,
                mediaEntites1.length,
                null,
            ),
        'entity-media-1':
            new Shopware.Data.EntityCollection(
                '/media',
                'media',
                null,
                null,
                mediaEntites2,
                mediaEntites2.length,
                null,
            )
    };

    describe('registerCmsElement', () => {
        it('registers cms element', () => {
            const elementName = 'test';
            const expected = {
                name: elementName,
                label: 'sw-cms.elements.test.label',
                component: 'sw-cms-el-test',
                configComponent: 'sw-cms-el-config-test',
                previewComponent: 'sw-cms-el-preview-test',
                defaultConfig: {
                    media: {
                        source: 'static',
                        value: null,
                        required: true,
                        entity: { name: 'media' },
                    },
                    tabletMedia: {
                        source: 'static',
                        value: null,
                        required: true,
                        entity: { name: 'media' },
                    },
                },
            };

            cmsService.registerCmsElement(expected);
            const elementConfig = cmsService.getCmsElementConfigByName(elementName);
            expect(elementConfig).toEqual(expected);

            const elementRegistry = cmsService.getCmsElementRegistry();
            expect(elementRegistry).toEqual({ [elementName]: expected });
        });

        it('registers cms element with own collect function', () => {
            const elementName = 'test';
            const expected = {
                name: elementName,
                component: 'sw-cms-el-test',
                collect: jest.fn(),
            };

            cmsService.registerCmsElement(expected);
            expect(expected.collect.mock).toBeTruthy();
        });

        it('does not register cms element if component is missing', () => {
            const elementName = 'testWithoutComponent';
            const expected = {
                name: elementName,
                label: 'sw-cms.elements.test.label',
                configComponent: 'sw-cms-el-config-test',
                previewComponent: 'sw-cms-el-preview-test',
                defaultConfig: {},
            };

            const result = cmsService.registerCmsElement(expected);
            expect(result).toBe(false);

            const elementConfig = cmsService.getCmsElementConfigByName(elementName);
            expect(elementConfig).toEqual(undefined);

            const elementRegistry = cmsService.getCmsElementRegistry();
            expect(elementRegistry[elementName]).toEqual(undefined);
        });
    });

    describe('registerCmsBlock', () => {
        it('registers cms block correctly', () => {
            const blockName = 'test';
            const expected = {
                name: blockName,
                label: 'sw-cms.blocks.text.test.label',
                category: 'text',
                component: 'sw-cms-block-test',
                previewComponent: 'sw-cms-preview-test',
                defaultConfig: {
                    marginBottom: '20px',
                    marginTop: '20px',
                    marginLeft: '20px',
                    marginRight: '20px',
                    sizingMode: 'boxed',
                },
                slots: {
                    content: 'text',
                },
            };

            cmsService.registerCmsBlock(expected);
            const blockConfig = cmsService.getCmsBlockConfigByName(blockName);
            expect(blockConfig).toEqual(expected);

            const blockRegistry = cmsService.getCmsBlockRegistry();
            expect(blockRegistry).toEqual({ [blockName]: expected });
        });

        it('does not register cms block when name is not defined', () => {
            const blockName = 'testWithoutComponent';
            const expected = {
                name: blockName,
                label: 'sw-cms.blocks.text.test.label',
                category: 'text',
                previewComponent: 'sw-cms-preview-test',
                defaultConfig: {},
                slots: {},
            };

            const result = cmsService.registerCmsBlock(expected);
            expect(result).toEqual(false);

            const blockConfig = cmsService.getCmsBlockConfigByName(blockName);
            expect(blockConfig).toEqual(undefined);

            const blockRegistry = cmsService.getCmsBlockRegistry();
            expect(blockRegistry[blockName]).toEqual(undefined);
        });
    });

    describe('collect', () => {
        it('adds multiple entity data when cms element defaultConfig properties have the same entity', () => {
            // cms element components call the initElementConfig() function from cms-service mixin
            // to add the defaultConfig properties to the config root level
            const element = {
                name: 'test',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'static',
                        value: 5,
                        required: true,
                        entity: { name: 'media' },
                    },
                    tabletMedia: {
                        source: 'static',
                        value: 6,
                        required: true,
                        entity: { name: 'media' },
                    },
                },
            };

            cmsService.registerCmsElement(element);
            const result = element.collect(element);

            // search criteria gets optimized to only search the needed ids.
            const entityMedia2Criteria = new Shopware.Data.Criteria(1, 25);
            entityMedia2Criteria.setIds([5]);

            const entityMedia3Criteria = new Shopware.Data.Criteria(1, 25);
            entityMedia3Criteria.setIds([6]);

            const expected = {
                'entity-media-0': {
                    value: [5],
                    key: 'media',
                    name: 'media',
                    searchCriteria: entityMedia2Criteria,
                },
                'entity-media-1': {
                    value: [6],
                    key: 'tabletMedia',
                    name: 'media',
                    searchCriteria: entityMedia3Criteria,
                }
            };

            expect(result).toEqual(expected);
        });

        it('skips config key with source equal to "mapped" or "default"', () => {
            // cms element components call the initElementConfig() function from cms-service mixin
            // to add the defaultConfig properties to the config root level
            const element = {
                name: 'testWithSourceMapped',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'mapped',
                        value: 5,
                        required: true,
                        entity: { name: 'media' },
                    },
                },
            };

            cmsService.registerCmsElement(element);
            const result = element.collect(element);

            expect(result).toEqual({});
        });

        it('skips config key if no entity is defined', () => {
            // cms element components call the initElementConfig() function from cms-service mixin
            // to add the defaultConfig properties to the config root level
            const element = {
                name: 'testWithoutEntity',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'static',
                        value: 5,
                        required: true,
                    },
                },
            };

            cmsService.registerCmsElement(element);
            const result = element.collect(element);

            expect(result).toEqual({});
        });

        it('adds multiple value data if multiple entity values are given in a slot', () => {
            // cms element components call the initElementConfig() function from cms-service mixin
            // to add the defaultConfig properties to the config root level
            const element = {
                name: 'testWithMultipleEntityValues',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'static',
                        value: ['123', '567'],
                        required: true,
                        entity: { name: 'media' },
                    },
                },
            };

            cmsService.registerCmsElement(element);
            const result = element.collect(element);

            // search criteria gets optimized to only search the needed ids.
            const entityMedia2Criteria = new Shopware.Data.Criteria(1, 25);
            entityMedia2Criteria.setIds(['123', '567']);

            expect(result).toEqual({
                'entity-media-0': {
                    value: ['123', '567'],
                    key: 'media',
                    name: 'media',
                    searchCriteria: entityMedia2Criteria,
                }
            });
        });

        it('uses given search criteria of cms element', () => {
            const criteria = new Shopware.Data.Criteria(1, 10);
            criteria.setIds(['123']);
            // cms element components call the initElementConfig() function from cms-service mixin
            // to add the defaultConfig properties to the config root level
            const element = {
                name: 'testWithCriteria',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'static',
                        value: '123',
                        required: true,
                        entity: {
                            name: 'media',
                            criteria: criteria,
                        },
                    },
                },
            };

            cmsService.registerCmsElement(element);
            const result = element.collect(element);

            expect(result).toEqual({
                'entity-media-0': {
                    value: ['123'],
                    key: 'media',
                    name: 'media',
                    criteria: criteria,
                    searchCriteria: criteria,
                }
            });
        });

        it('adds multiple value data if multiple entity values with mediaId are given in a slot', () => {
            // cms element components call the initElementConfig() function from cms-service mixin
            // to add the defaultConfig properties to the config root level
            const element = {
                name: 'testWithMultipleEntityValuesWithMediaId',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'static',
                        value: [{ mediaId: '123' }, { mediaId: '567' }],
                        required: true,
                        entity: { name: 'media' },
                    },
                },
            };

            cmsService.registerCmsElement(element);
            const result = element.collect(element);

            // search criteria gets optimized to only search the needed ids.
            const entityMedia2Criteria = new Shopware.Data.Criteria(1, 25);
            entityMedia2Criteria.setIds(['123', '567']);

            expect(result).toEqual({
                'entity-media-0': {
                    value: ['123', '567'],
                    key: 'media',
                    name: 'media',
                    searchCriteria: entityMedia2Criteria,
                }
            });
        });
    });

    describe('enrich', () => {
        it('uses given enrich function from cms element', () => {
            const elementName = 'test';
            const expected = {
                name: elementName,
                component: 'sw-cms-el-test',
                enrich: jest.fn(),
            };

            cmsService.registerCmsElement(expected);
            expect(expected.enrich.mock).toBeTruthy();
        });

        it('adds multiple entity data when cms element defaultConfig properties have the same entity', () => {
            // cms element components call the initElementConfig() and initElementData() functions from cms-service mixin
            // to add the defaultConfig and defaultData properties to the config root level
            const element = {
                name: 'test',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'static',
                        value: '123',
                        required: true,
                        entity: { name: 'media' },
                    },
                    tabletMedia: {
                        source: 'static',
                        value: '567',
                        required: true,
                        entity: { name: 'media' },
                    },
                },
                data: { media: {} },
            };

            cmsService.registerCmsElement(element);

            element.enrich(element, enrichData);

            expect(element.data).toEqual({
                media: {
                    filtered: true,
                    id: '123',
                    name: 'media',
                },
                tabletMedia: {
                    filtered: true,
                    id: '567',
                    name: 'media',
                },
            });
        });

        it('returns when element data is not defined', () => {
            // cms element components call the initElementConfig() and initElementData() functions from cms-service mixin
            // to add the defaultConfig and defaultData properties to the config root level
            const element = {
                name: 'testEnrichWithoutData',
                component: 'sw-cms-el-test',
                config: {},
            };

            cmsService.registerCmsElement(element);
            element.enrich(element, {});

            expect(element.data).toEqual(undefined);
        });

        it('adds no entity data when cms element defaultConfig property has no entity defined', () => {
            // cms element components call the initElementConfig() and initElementData() functions from cms-service mixin
            // to add the defaultConfig and defaultData properties to the config root level
            const element = {
                name: 'testWithoutEntity',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'static',
                        value: '123',
                        required: true,
                        // entity is not defined
                    },
                    tabletMedia: {
                        source: 'static',
                        value: '567',
                        required: true,
                        entity: { name: 'media' },
                    },
                },
                data: { media: {} },
            };

            cmsService.registerCmsElement(element);

            element.enrich(element, {
                'entity-media-0':
                    new Shopware.Data.EntityCollection(
                        '/media',
                        'media',
                        null,
                        null,
                        mediaEntites2,
                        mediaEntites2.length,
                        null,
                    )
            });

            expect(element.data).toEqual({
                media: {},
                tabletMedia: {
                    filtered: true,
                    id: '567',
                    name: 'media',
                },
            });
        });

        it('returns if data has no fitting Object key', () => {
            // cms element components call the initElementConfig() function from cms-service mixin
            // to add the defaultConfig properties to the config root level
            const element = {
                name: 'testCollectWithoutFittingDataKey',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'source',
                        value: 5,
                        required: true,
                        entity: { name: 'media' },
                    },
                },
                data: { media: {} },
            };

            cmsService.registerCmsElement(element);

            element.enrich(element, { 'not-fitting-key': {} });

            expect(element.data).toEqual({ media: {} });
        });

        it('adds multiple media data if value array defined', () => {
            // cms element components call the initElementConfig() and initElementData() functions from cms-service mixin
            // to add the defaultConfig and defaultData properties to the config root level
            const element = {
                name: 'testValueArray',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'static',
                        value: ['123', '567'],
                        required: true,
                        entity: { name: 'media' },
                    },
                },
                data: {
                    media: {},
                },
            };

            cmsService.registerCmsElement(element);

            const mediaEntites = [
                {
                    id: '123',
                    name: 'media',
                    filtered: true,
                },
                {
                    id: '567',
                    name: 'media',
                    filtered: true,
                }
            ];

            element.enrich(
                element,
                {
                    'entity-media-0':
                        new Shopware.Data.EntityCollection(
                            '/media',
                            'media',
                            null,
                            null,
                            mediaEntites,
                            mediaEntites.length,
                            null,
                        ),
                },
            );

            expect(element.data).toEqual({
                media: [{
                    filtered: true,
                    id: '123',
                    name: 'media',
                },
                {
                    filtered: true,
                    id: '567',
                    name: 'media',
                }],
            });
        });
    });

    describe('getEntityMappingTypes', () => {
        const entityFactory = Shopware.Application.getContainer('factory').entity;

        it('does not return entity mapping types if entity name is null', () => {
            const result = cmsService.getEntityMappingTypes();
            expect(result).toEqual({});
        });

        it('does not return entity mapping types if entity schema is undefined', () => {
            const result = cmsService.getEntityMappingTypes('undefined');
            expect(result).toEqual({});
        });

        it('does return entity mapping types direclty if already mapped', () => {
            const testAlreadyMappedType = {
                properties: {
                    id: {
                        type: 'uuid',
                    },
                },
            };

            entityFactory.addEntityDefinition('testAlreadyMappedType', testAlreadyMappedType);
            cmsService.getEntityMappingTypes('testAlreadyMappedType');

            // returns directly without calling handlePropertyMappings()
            const result2 = cmsService.getEntityMappingTypes('testAlreadyMappedType');

            expect(result2).toEqual({
                uuid: ['testAlreadyMappedType.id'],
            });
        });

        it('does not return entity mapping types if property is read only', () => {
            const testReadOnly = {
                properties: {
                    id: {
                        type: 'uuid',
                        readOnly: true,
                    },
                },
            };

            entityFactory.addEntityDefinition('testReadOnly', testReadOnly);
            const result = cmsService.getEntityMappingTypes('testReadOnly');
            expect(result).toEqual({});
        });

        it('does not return entity mapping types if property format is on block list', () => {
            const testFormatBlocklist = {
                properties: {
                    id: {
                        type: 'uuid',
                        format: 'uuid',
                    },
                },
            };

            entityFactory.addEntityDefinition('testFormatBlocklist', testFormatBlocklist);
            const result = cmsService.getEntityMappingTypes('testFormatBlocklist');
            expect(result).toEqual({});
        });

        it('returns entity mapping types if property type is object and entity schema is undefined', () => {
            const testTypeObjectAndEntityUndefined = {
                properties: {
                    id: {
                        type: 'object',
                        entity: 'undefined',
                    },
                }
            };

            entityFactory.addEntityDefinition('testTypeObjectAndEntityUndefined', testTypeObjectAndEntityUndefined);
            const result = cmsService.getEntityMappingTypes('testTypeObjectAndEntityUndefined');
            expect(result).toEqual({
                entity: {
                    undefined: ['testTypeObjectAndEntityUndefined.id'],
                },
            });
        });

        it('returns entity mapping types if property type is array and entity is already mapped', () => {
            const testTypeArrayAlreadyMapped = {
                properties: {
                    id: {
                        type: 'array',
                        entity: 'testTypeArrayAlreadyMapped',
                        properties: {
                            id: {
                                type: 'uuid',
                            },
                        },
                    },
                }
            };

            entityFactory.addEntityDefinition('testTypeArrayAlreadyMapped', testTypeArrayAlreadyMapped);
            const result = cmsService.getEntityMappingTypes('testTypeArrayAlreadyMapped');
            expect(result).toEqual({
                entity: {
                    testTypeArrayAlreadyMapped: ['testTypeArrayAlreadyMapped.id'],
                },
            });
        });

        it('does not return entity mapping types if type is array and no entity is defined', () => {
            const testTypeArrayNoEntity = {
                properties: {
                    id: {
                        type: 'array',
                    },
                }
            };

            entityFactory.addEntityDefinition('testTypeArrayNoEntity', testTypeArrayNoEntity);
            const result = cmsService.getEntityMappingTypes('testTypeArrayNoEntity');
            expect(result).toEqual({});
        });

        it('returns entity mapping types if property type is not array nor object and type already mapped', () => {
            const testTypeIsEntityAndAlreadyMapped = {
                properties: {
                    property1: {
                        type: 'testTypeIsEntityAndAlreadyMapped',
                    },
                    property2: {
                        type: 'testTypeIsEntityAndAlreadyMapped',
                    },
                }
            };

            entityFactory.addEntityDefinition('testTypeIsEntityAndAlreadyMapped', testTypeIsEntityAndAlreadyMapped);
            const result = cmsService.getEntityMappingTypes('testTypeIsEntityAndAlreadyMapped');
            expect(result).toEqual({
                testTypeIsEntityAndAlreadyMapped: [
                    'testTypeIsEntityAndAlreadyMapped.property1',
                    'testTypeIsEntityAndAlreadyMapped.property2',
                ],
            });
        });

        it('returns entity mapping types if property type array and entity already mapped', () => {
            const testTypeIsArrayAndAlreadyMapped = {
                properties: {
                    property1: {
                        type: 'object',
                        entity: 'testTypeIsArrayAndAlreadyMapped',
                    },
                    property2: {
                        type: 'array',
                        entity: 'testTypeIsArrayAndAlreadyMapped',
                    },
                }
            };

            entityFactory.addEntityDefinition('testTypeIsArrayAndAlreadyMapped', testTypeIsArrayAndAlreadyMapped);
            const result = cmsService.getEntityMappingTypes('testTypeIsArrayAndAlreadyMapped');
            expect(result).toEqual({
                entity: {
                    testTypeIsArrayAndAlreadyMapped: [
                        'testTypeIsArrayAndAlreadyMapped.property1',
                        'testTypeIsArrayAndAlreadyMapped.property1.property1',
                        'testTypeIsArrayAndAlreadyMapped.property1.property2',
                        'testTypeIsArrayAndAlreadyMapped.property2',
                    ],
                },
            });
        });

        it('returns entity mapping types if type is object and entity is not defined but nested properties', () => {
            const testOnlyProperties = {
                properties: {
                    id: {
                        type: 'object',
                        properties: {
                            mediaProperty: {
                                type: 'object',
                                entity: 'media',
                            },
                        }
                    },
                },
            };

            entityFactory.addEntityDefinition('testOnlyProperties', testOnlyProperties);
            const result = cmsService.getEntityMappingTypes('testOnlyProperties');
            expect(result).toEqual({
                entity: {
                    media: ['testOnlyProperties.id.mediaProperty'],
                }
            });
        });

        it('does not return entity mapping types if type is object and entity nor nested properties are defined', () => {
            const testWithoutPropertiesAndEntity = {
                properties: {
                    id: {
                        type: 'object',
                    },
                },
            };

            entityFactory.addEntityDefinition('testWithoutPropertiesAndEntity', testWithoutPropertiesAndEntity);
            const result = cmsService.getEntityMappingTypes('testWithoutPropertiesAndEntity');
            expect(result).toEqual({});
        });

        it('returns entity mapping types if property type is object and schema is defined', () => {
            const testTypeObjectWithSchema = {
                properties: {
                    id: {
                        type: 'object',
                        entity: 'media',
                    },
                },
            };

            const media = {
                properties: {
                    mediaProperty: {
                        type: 'uuid',
                    },
                },
            };

            entityFactory.addEntityDefinition('media', media);
            entityFactory.addEntityDefinition('testTypeObjectWithSchema', testTypeObjectWithSchema);
            const result = cmsService.getEntityMappingTypes('testTypeObjectWithSchema');
            expect(result).toEqual({
                entity: {
                    media: ['testTypeObjectWithSchema.id'],
                },
                uuid: ['testTypeObjectWithSchema.id.mediaProperty']
            });
        });
    });

    describe('getPropertyByMappingPath', () => {
        it('returns the property of a given entity by path', () => {
            const entity = new Entity('test', 'product_manufacturer', {
                description: 'manufacturer-description',
                name: 'manufacturer',
                something: 'abc',
                test: {
                    something: 'xyz',
                }
            });

            const result = cmsService.getPropertyByMappingPath(entity, 'test.something');
            expect(result).toEqual('abc');
        });

        it('returns null if property is not defined', () => {
            const entity = new Entity('test', 'product_manufacturer', {});

            const result = cmsService.getPropertyByMappingPath(entity, 'test.something');
            expect(result).toEqual(null);
        });

        it('returns translated if exists', () => {
            const entity = new Entity('test', 'product_manufacturer', {
                description: 'manufacturer-description',
                name: 'manufacturer',
                translated: { name: 'manufacturer-translated' },
            });

            const result = cmsService.getPropertyByMappingPath(entity, 'translated.name');
            expect(result).toEqual('manufacturer-translated');
        });
    });

    describe('getCollectFunction', () => {
        const context = {
            ...Shopware.Context.api,
            inheritance: true,
        };

        it('adds multiple entity data when cms element defaultConfig properties have the same entity', () => {
            // cms element components call the initElementConfig() function from cms-service mixin
            // to add the defaultConfig properties to the config root level
            const element = {
                name: 'testGetCollectFunction',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'static',
                        value: 5,
                        required: true,
                        entity: { name: 'media' },
                    },
                    tabletMedia: {
                        source: 'static',
                        value: 6,
                        required: true,
                        entity: { name: 'media' },
                    },
                },
                collect: cmsService.getCollectFunction(),
            };

            cmsService.registerCmsElement(element);
            const result = element.collect(element);

            // search criteria gets optimized to only search the needed ids.
            const entityMedia2Criteria = new Shopware.Data.Criteria(1, 25);
            entityMedia2Criteria.setIds([5]);

            const entityMedia3Criteria = new Shopware.Data.Criteria(1, 25);
            entityMedia3Criteria.setIds([6]);

            const expected = {
                'entity-media-0': {
                    value: [5],
                    key: 'media',
                    name: 'media',
                    searchCriteria: entityMedia2Criteria,
                    context,
                },
                'entity-media-1': {
                    value: [6],
                    key: 'tabletMedia',
                    name: 'media',
                    searchCriteria: entityMedia3Criteria,
                    context,
                }
            };

            expect(result).toEqual(expected);
        });

        it('skips config key with source equal to "mapped" or "default"', () => {
            // cms element components call the initElementConfig() function from cms-service mixin
            // to add the defaultConfig properties to the config root level
            const element = {
                name: 'testGetCollectFunctionWithSourceMapped',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'mapped',
                        value: 5,
                        required: true,
                        entity: { name: 'media' },
                    },
                },
                collect: cmsService.getCollectFunction(),
            };

            cmsService.registerCmsElement(element);
            const result = element.collect(element);

            expect(result).toEqual({});
        });

        it('skips config key if no entity is defined', () => {
            // cms element components call the initElementConfig() function from cms-service mixin
            // to add the defaultConfig properties to the config root level
            const element = {
                name: 'testGetCollectFunctionWithoutEntity',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'static',
                        value: 5,
                        required: true,
                    },
                },
                collect: cmsService.getCollectFunction(),
            };

            cmsService.registerCmsElement(element);
            const result = element.collect(element);

            expect(result).toEqual({});
        });

        it('uses given search criteria of cms element', () => {
            const criteria = new Shopware.Data.Criteria(1, 10);
            criteria.setIds(['123']);
            // cms element components call the initElementConfig() function from cms-service mixin
            // to add the defaultConfig properties to the config root level
            const element = {
                name: 'testGetCollectFunctionWithCriteria',
                component: 'sw-cms-el-test',
                config: {
                    media: {
                        source: 'static',
                        value: '123',
                        required: true,
                        entity: {
                            name: 'media',
                            criteria: criteria,
                        },
                    },
                },
                collect: cmsService.getCollectFunction(),
            };

            cmsService.registerCmsElement(element);
            const result = element.collect(element);

            expect(result).toEqual({
                'entity-media-0': {
                    value: ['123'],
                    key: 'media',
                    name: 'media',
                    criteria,
                    searchCriteria: criteria,
                    context,
                }
            });
        });
    });
});
