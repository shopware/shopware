import './component';
import './config';
import './preview';

const Criteria = Shopware.Data.Criteria;
const criteria = new Criteria(1, 25);
criteria.addAssociation('crossSellings.assignedProducts.product');

Shopware.Service('cmsService').registerCmsElement({
    name: 'cross-selling',
    label: 'sw-cms.elements.crossSelling.label',
    component: 'sw-cms-el-cross-selling',
    configComponent: 'sw-cms-el-config-cross-selling',
    previewComponent: 'sw-cms-el-preview-cross-selling',
    defaultConfig: {
        product: {
            source: 'static',
            value: null,
            required: true,
            entity: {
                name: 'product',
                criteria: criteria,
            },
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
        boxLayout: {
            source: 'static',
            value: 'standard',
        },
        elMinWidth: {
            source: 'static',
            value: '300px',
        },
    },
    collect: Shopware.Service('cmsService').getCollectFunction(),
});
