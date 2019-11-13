import './component';
import './config';
import './preview';

const Criteria = Shopware.Data.Criteria;
const criteria = new Criteria();
criteria.addAssociation('cover');

Shopware.Service('cmsService').registerCmsElement({
    name: 'product-box',
    label: 'sw-cms.elements.productBox.label',
    component: 'sw-cms-el-product-box',
    previewComponent: 'sw-cms-el-preview-product-box',
    configComponent: 'sw-cms-el-config-product-box',
    defaultConfig: {
        product: {
            source: 'static',
            value: null,
            required: true,
            entity: {
                name: 'product',
                criteria: criteria
            }
        },
        boxLayout: {
            source: 'static',
            value: 'standard'
        },
        displayMode: {
            source: 'static',
            value: 'standard'
        },
        verticalAlign: {
            source: 'static',
            value: null
        }
    },
    defaultData: {
        boxLayout: 'standard',
        product: {
            name: 'Lorem Ipsum dolor',
            description: `Lorem ipsum dolor sit amet, consetetur sadipscing elitr,
                          sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat,
                          sed diam voluptua.`.trim(),
            price: [
                { gross: 19.90 }
            ],
            cover: {
                media: {
                    url: '/administration/static/img/cms/preview_glasses_large.jpg',
                    alt: 'Lorem Ipsum dolor'
                }
            }
        }
    },
    collect: function collect(elem) {
        const context = Object.assign(
            {},
            Shopware.Context.api,
            { inheritance: true }
        );

        const criteriaList = {};

        Object.keys(elem.config).forEach((configKey) => {
            if (elem.config[configKey].source === 'mapped') {
                return;
            }

            const entity = elem.config[configKey].entity;

            if (entity && elem.config[configKey].value) {
                const entityKey = entity.name;
                const entityData = {
                    value: [elem.config[configKey].value],
                    key: configKey,
                    searchCriteria: entity.criteria ? entity.criteria : new Criteria(),
                    ...entity
                };

                entityData.searchCriteria.setIds(entityData.value);
                entityData.context = context;

                criteriaList[`entity-${entityKey}`] = entityData;
            }
        });

        return criteriaList;
    }
});
