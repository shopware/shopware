import './component';
import './config';
import './preview';

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
    },
    enrich: function enrich(elem, data) {
        if (Object.keys(data).length < 1) {
            return;
        }

        Object.keys(elem.config).forEach((configKey) => {
            const entity = elem.config[configKey].entity;

            if (!entity) {
                return;
            }

            const entityKey = entity.name;
            if (!data[`entity-${entityKey}`]) {
                return;
            }

            elem.data[configKey] = [];
            elem.config[configKey].value.forEach((sliderItem) => {
                elem.data[configKey].push({
                    newTab: sliderItem.newTab,
                    url: sliderItem.url,
                    media: data[`entity-${entityKey}`].get(sliderItem.mediaId),
                });
            });
        });
    },
});
