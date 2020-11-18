import './component';
import './config';

Shopware.Service('cmsService').registerCmsElement({
    flag: Shopware.Feature.isActive('FEATURE_NEXT_10078'),
    name: 'manufacturer-logo',
    label: 'sw-cms.elements.productHeading.logo.label',
    hidden: !Shopware.Feature.isActive('FEATURE_NEXT_10078'),
    component: 'sw-cms-el-manufacturer-logo',
    configComponent: 'sw-cms-el-config-manufacturer-logo',
    defaultConfig: {
        media: {
            source: 'static',
            value: null,
            required: true,
            entity: {
                name: 'media'
            }
        },
        displayMode: {
            source: 'static',
            value: 'cover'
        },
        url: {
            source: 'static',
            value: null
        },
        newTab: {
            source: 'static',
            value: true
        },
        minHeight: {
            source: 'static',
            value: null
        },
        verticalAlign: {
            source: 'static',
            value: null
        }
    }
});
