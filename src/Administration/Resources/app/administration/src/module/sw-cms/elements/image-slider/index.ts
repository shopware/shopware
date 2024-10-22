/**
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-el-preview-image-slider', () => import('./preview'));
/**
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-el-config-image-slider', () => import('./config'));
/**
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-el-image-slider', () => import('./component'));

type ImageSliderItemConfig = {
    newTab: boolean;
    url: string;
    mediaId: string;
};

type ImageSliderItem = {
    newTab: boolean;
    url: string;
    media: EntitySchema.Entity<'media'> | null;
};

/**
 * @private
 * @package buyers-experience
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'image-slider',
    label: 'sw-cms.elements.imageSlider.label',
    component: 'sw-cms-el-image-slider',
    configComponent: 'sw-cms-el-config-image-slider',
    previewComponent: 'sw-cms-el-preview-image-slider',
    defaultConfig: {
        sliderItems: {
            source: 'static',
            value: [],
            required: true,
            entity: {
                name: 'media',
            },
        },
        navigationArrows: {
            source: 'static',
            value: 'outside',
        },
        navigationDots: {
            source: 'static',
            value: null,
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
        minHeight: {
            source: 'static',
            value: '300px',
        },
        verticalAlign: {
            source: 'static',
            value: null,
        },
        speed: {
            value: 300,
            source: 'static',
        },
        autoSlide: {
            value: false,
            source: 'static',
        },
        autoplayTimeout: {
            value: 5000,
            source: 'static',
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
            entityCount += 1;

            if (!data[entityKey]) {
                return;
            }

            Object.assign(slot.data, {
                [configKey]: [],
            });

            const items = slot.data[configKey] as unknown as ImageSliderItem[];
            const config = slot.config[configKey];

            if (!Array.isArray(config.value)) {
                return;
            }

            config.value.forEach((sliderItem: ImageSliderItemConfig) => {
                const item: ImageSliderItem = {
                    newTab: sliderItem.newTab,
                    url: sliderItem.url,
                    media: data[entityKey].get(sliderItem.mediaId) as EntitySchema.Entity<'media'> | null,
                };

                items.push(item);
            });
        });
    },
});
