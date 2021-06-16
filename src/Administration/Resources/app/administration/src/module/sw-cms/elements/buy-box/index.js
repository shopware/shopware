import './component';
import './config';
import './preview';

const Criteria = Shopware.Data.Criteria;
const criteria = new Criteria();
criteria.addAssociation('deliveryTime');

Shopware.Service('cmsService').registerCmsElement({
    name: 'buy-box',
    label: 'sw-cms.elements.buyBox.label',
    component: 'sw-cms-el-buy-box',
    configComponent: 'sw-cms-el-config-buy-box',
    previewComponent: 'sw-cms-el-preview-buy-box',
    disabledConfigInfoTextKey: 'sw-cms.elements.buyBox.infoText.tooltipSettingDisabled',
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
    defaultData: {
        product: {
            name: 'Lorem Ipsum dolor',
            productNumber: 'XXXXXX',
            minPurchase: 1,
            deliveryTime: {
                name: '1-3 days',
            },
            price: [
                { gross: 0.00 },
            ],
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
