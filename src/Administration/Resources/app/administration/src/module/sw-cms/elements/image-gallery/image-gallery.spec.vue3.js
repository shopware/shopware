/**
 * @package buyers-experience
 */
import 'src/module/sw-cms/service/cms.service';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/image-gallery';

describe('module/sw-cms/elements/image-gallery/index.js', () => {
    const cmsService = Shopware.Service('cmsService');
    const elementRegistry = cmsService.getCmsElementRegistry();
    const element = elementRegistry['image-gallery'];

    it('registers image-gallery cms element', async () => {
        const elementConfig = cmsService.getCmsElementConfigByName('image-gallery');
        expect(elementConfig.name).toBe('image-gallery');
    });

    it('returns empty object because config values are set to null', async () => {
        const result = element.enrich(element, {});
        expect(result).toBeUndefined();
    });

    it('adds multiple entity data when cms element defaultConfig properties have the same entity', async () => {
        element.defaultConfig.sliderItems.value = [{
            mediaId: '123',
            newTab: true,
            url: 'https://www.shopware.com',
        }];

        element.defaultConfig.mediaProperty = {
            entity: { name: 'media' },
            source: 'static',
            value: [{
                mediaId: '567',
                newTab: false,
                url: 'https://www.google.com',
            }],
        };

        // cms element components call the initElementConfig() function from cms-service mixin
        // to add the defaultConfig properties to the config root level
        element.config = element.defaultConfig;
        element.data = {};

        cmsService.registerCmsElement(element);

        const mediaEntites1 = [{
            id: '123',
            url: 'https://www.shopware.com',
        }];

        const mediaEntites2 = [{
            id: '567',
            url: 'https://www.google.com',
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
                ),
        };
        element.enrich(element, enrichData);

        expect(element.data).toEqual({
            sliderItems: [{
                media: mediaEntites1[0],
                newTab: true,
                url: 'https://www.shopware.com',
            }],
            mediaProperty: [{
                media: mediaEntites2[0],
                newTab: false,
                url: 'https://www.google.com',
            }],
        });
    });

    it('skips config property if enrich data has no fitting key', async () => {
        // cms element components call the initElementConfig() function from cms-service mixin
        // to add the defaultConfig properties to the config root level
        element.config = element.defaultConfig;
        element.data = {};

        cmsService.registerCmsElement(element);

        element.enrich(element, {
            'entity-media-xyz': null,
        });

        expect(element.data).toEqual({});
    });
});
