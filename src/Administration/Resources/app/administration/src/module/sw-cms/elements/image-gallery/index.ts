/**
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-el-preview-image-gallery', () => import('./preview'));
/**
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-el-config-image-gallery', () => import('./config'));
/**
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-el-image-gallery', () => import('./component'));

type ImageGalleryItemConfig = {
    newTab: boolean;
    url: string;
    mediaId: string;
};

type ImageGalleryItem = {
    newTab: boolean;
    url: string;
    media: EntitySchema.Entity<'media'> | null;
};

/**
 * @private
 * @package buyers-experience
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'image-gallery',
    label: 'sw-cms.elements.imageGallery.label',
    component: 'sw-cms-el-image-gallery',
    configComponent: 'sw-cms-el-config-image-gallery',
    previewComponent: 'sw-cms-el-preview-image-gallery',

    defaultConfig: {
        sliderItems: {
            source: 'static',
            value: [],
            type: Array,
            required: true,
            entity: {
                name: 'media',
            },
        },
        navigationArrows: {
            source: 'static',
            value: 'inside',
        },
        navigationDots: {
            source: 'static',
            value: null,
        },
        galleryPosition: {
            source: 'static',
            value: 'left',
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
        minHeight: {
            source: 'static',
            value: '340px',
        },
        verticalAlign: {
            source: 'static',
            value: null,
        },
        zoom: {
            source: 'static',
            value: false,
        },
        fullScreen: {
            source: 'static',
            value: false,
        },
        keepAspectRatioOnZoom: {
            source: 'static',
            value: true,
        },
        magnifierOverGallery: {
            source: 'static',
            value: false,
        },
    },
    enrich: function enrich(slot, data) {
        if (Object.keys(data).length < 1) {
            return;
        }

        let entityCount = 0;
        Object.keys(slot.config).forEach((configKey) => {
            const entity = slot.config[configKey].entity;

            if (!entity) {
                return;
            }

            const entityKey = `entity-${entity.name}-${entityCount}`;

            if (!data[entityKey]) {
                return;
            }

            entityCount += 1;

            Object.assign(slot.data, {
                [configKey]: [] as ImageGalleryItem[],
            });

            const items = slot.data[configKey] as unknown as ImageGalleryItem[];
            const config = slot.config[configKey];

            if (!Array.isArray(config.value)) {
                return;
            }

            config.value.forEach((sliderItem: ImageGalleryItemConfig) => {
                const item: ImageGalleryItem = {
                    newTab: sliderItem.newTab,
                    url: sliderItem.url,
                    media: data[entityKey].get(sliderItem.mediaId) as EntitySchema.Entity<'media'> | null,
                };

                items.push(item);
            });
        });
    },
});
