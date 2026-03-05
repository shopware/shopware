/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-preview-media-gallery', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-config-media-gallery', () => import('./config'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-media-gallery', () => import('./component'));

type MediaGalleryItemConfig = {
    mediaId: string;
    mediaUrl: string;
    url: string | null;
    newTab: boolean;
};

type MediaGalleryItem = {
    media: Entity<'media'> | null;
    url: string | null;
    newTab: boolean;
};

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'media-gallery',
    label: 'sw-cms.elements.mediaGallery.label',
    component: 'sw-cms-el-media-gallery',
    configComponent: 'sw-cms-el-config-media-gallery',
    previewComponent: 'sw-cms-el-preview-media-gallery',

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
        displayMode: {
            source: 'static',
            value: 'auto',
        },
        maxHeight: {
            source: 'static',
            value: null,
        },
        showNavigationArrows: {
            source: 'static',
            value: false,
        },
        thumbnailPosition: {
            source: 'static',
            value: 'left',
        },
        showMagnifier: {
            source: 'static',
            value: true,
        },
        showFullScreenGallery: {
            source: 'static',
            value: true,
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
                [configKey]: [] as MediaGalleryItem[],
            });

            const items = slot.data[configKey] as unknown as MediaGalleryItem[];
            const config = slot.config[configKey];

            if (!Array.isArray(config.value)) {
                return;
            }

            config.value.forEach((sliderItem: MediaGalleryItemConfig) => {
                const item: MediaGalleryItem = {
                    media: data[entityKey].get(sliderItem.mediaId) as Entity<'media'> | null,
                    url: sliderItem.url,
                    newTab: sliderItem.newTab,
                };

                items.push(item);
            });
        });
    },
});
