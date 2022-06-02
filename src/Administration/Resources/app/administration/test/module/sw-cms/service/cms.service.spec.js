import 'src/module/sw-cms/service/cms.service';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import Entity from 'src/core/data/entity.data';

describe('module/sw-cms/service/cms.service.spec.js', () => {
    const cmsService = Shopware.Service('cmsService');

    it('test it registers cms element', () => {
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
                    entity: {
                        name: 'media',
                    },
                },
                tabletMedia: {
                    source: 'static',
                    value: null,
                    required: true,
                    entity: {
                        name: 'media',
                    },
                },
            }
        };

        cmsService.registerCmsElement(expected);
        const elementConfig = cmsService.getCmsElementConfigByName(elementName);
        expect(elementConfig).toEqual(expected);

        const elementRegistry = cmsService.getCmsElementRegistry();
        expect(elementRegistry).toEqual({ [elementName]: expected });
    });

    it('test it registers cms block', () => {
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

    it('collect function adds multiple entity data when cms element defaultConfig properties have the same entity', () => {
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
                    entity: {
                        name: 'media',
                    },
                },
                tabletMedia: {
                    source: 'static',
                    value: 6,
                    required: true,
                    entity: {
                        name: 'media',
                    },
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
                searchCriteria: entityMedia2Criteria
            },
            'entity-media-1': {
                value: [6],
                key: 'tabletMedia',
                name: 'media',
                searchCriteria: entityMedia3Criteria
            }
        };

        expect(result).toEqual(expected);
    });

    it('enrich function adds multiple entity data when cms element defaultConfig properties have the same entity', () => {
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
                    entity: {
                        name: 'media',
                    },
                },
                tabletMedia: {
                    source: 'static',
                    value: '567',
                    required: true,
                    entity: {
                        name: 'media',
                    },
                },
            },
            data: {
                media: {}
            },
        };

        cmsService.registerCmsElement(element);

        const mediaEntites1 = [
            {
                id: '123',
                name: 'media',
                filtered: true
            }
        ];

        const mediaEntites2 = [
            {
                id: '567',
                name: 'media',
                filtered: true
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
                    mediaEntites1,
                    mediaEntites1.length,
                    null
                ),
                'entity-media-1':
                    new Shopware.Data.EntityCollection(
                        '/media',
                        'media',
                        null,
                        null,
                        mediaEntites2,
                        mediaEntites2.length,
                        null
                    )
            },
        );

        expect(element.data).toEqual({
            media: {
                filtered: true,
                id: '123',
                name: 'media'
            },
            tabletMedia: {
                filtered: true,
                id: '567',
                name: 'media'
            },
        });
    });

    it('returns entity mapping types', () => {
        const entityFactory = Shopware.Application.getContainer('factory').entity;
        const country = {
            entity: 'country',
            properties: {
                id: {
                    type: 'uuid'
                },
            }
        };

        entityFactory.addEntityDefinition('country', country);
        const result = cmsService.getEntityMappingTypes('country');
        expect(result).toEqual({ uuid: ['country.id'] });
    });

    it('returns the property of a given entity by path', () => {
        const entity = new Entity('test', 'product_manufacturer', {
            description: 'manufacturer-description',
            name: 'manufacturer',
            translated: { name: 'manufacturer-translated' }
        });

        const result = cmsService.getPropertyByMappingPath(entity, 'translated.name');
        expect(result).toEqual('manufacturer-translated');
    });
});
