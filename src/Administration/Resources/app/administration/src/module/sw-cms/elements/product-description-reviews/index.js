import './component';
import './config';
import './preview';

const Criteria = Shopware.Data.Criteria;
const criteria = new Criteria();
criteria.addAssociation('properties');

Shopware.Service('cmsService').registerCmsElement({
    name: 'product-description-reviews',
    label: 'sw-cms.elements.productDescriptionReviews.label',
    component: 'sw-cms-el-product-description-reviews',
    configComponent: 'sw-cms-el-config-product-description-reviews',
    previewComponent: 'sw-cms-el-preview-product-description-reviews',
    disabledConfigInfoTextKey: 'sw-cms.elements.productDescriptionReviews.infoText.descriptionAndReviewsElement',
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
        alignment: {
            source: 'static',
            value: null,
        },
    },
    collect: function collect(elem) {
        const context = {
            ...Shopware.Context.api,
            inheritance: true,
        };

        const criteriaList = {};

        Object.keys(elem.config).forEach((configKey) => {
            if (elem.config[configKey].source === 'mapped') {
                return;
            }

            const config = elem.config[configKey];
            const configEntity = config.entity;
            const configValue = config.value;

            if (!configEntity || !configValue) {
                return;
            }


            const entityKey = configEntity.name;
            const entityData = {
                value: [configValue],
                key: configKey,
                searchCriteria: configEntity.criteria ? configEntity.criteria : new Criteria(),
                ...configEntity,
            };

            entityData.searchCriteria.setIds(entityData.value);
            entityData.context = context;

            criteriaList[`entity-${entityKey}`] = entityData;
        });

        return criteriaList;
    },
});
