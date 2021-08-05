const { remove } = Shopware.Utils.array;
const { Service } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @module app/service/custom-field
 */

/**
 *
 * @memberOf module:core/service/custom-field
 * @constructor
 * @method createCustomFieldTypeService
 * @returns {Object}
 */
export default function createCustomFieldService() {
    const $typeStore = {
        select: {
            configRenderComponent: 'sw-custom-field-type-select',
            config: {},
        },
        entity: {
            configRenderComponent: 'sw-custom-field-type-entity',
            type: 'select',
            config: {},
        },
        text: {
            configRenderComponent: 'sw-custom-field-type-text',
            type: 'text',
            config: {
                componentName: 'sw-field',
                type: 'text',
            },
        },
        media: {
            configRenderComponent: 'sw-custom-field-type-base',
            type: 'text',
            config: {
                componentName: 'sw-media-field',
            },
        },
        number: {
            configRenderComponent: 'sw-custom-field-type-number',
            type: 'int',
            config: {
                componentName: 'sw-field',
                type: 'number',
                numberType: 'float',
            },
        },
        date: {
            configRenderComponent: 'sw-custom-field-type-date',
            type: 'datetime',
            config: {
                componentName: 'sw-field',
                type: 'date',
                dateType: 'datetime',
            },
        },
        checkbox: {
            configRenderComponent: 'sw-custom-field-type-checkbox',
            type: 'bool',
            config: {
                componentName: 'sw-field',
                type: 'checkbox',
            },
        },
        switch: {
            configRenderComponent: 'sw-custom-field-type-checkbox',
            type: 'bool',
            config: {
                componentName: 'sw-field',
                type: 'switch',
            },
        },
        textEditor: {
            configRenderComponent: 'sw-custom-field-type-text-editor',
            type: 'html',
            config: {
                componentName: 'sw-text-editor',
            },
        },
        colorpicker: {
            configRenderComponent: 'sw-custom-field-type-base',
            type: 'text',
            config: {
                componentName: 'sw-field',
                type: 'colorpicker',
            },
        },
    };

    const $entityNameStore = [
        'category',
        'product',
        'product_manufacturer',
        'customer',
        'customer_address',
        'order',
        'sales_channel',
        'media',
        'landing_page',
        'promotion',
        'product_stream',
        'property_group',
        'product_review',
        'event_action',
        'country',
        'currency',
        'customer_group',
        'delivery_time',
        'document_base_config',
        'language',
        'number_range',
        'payment_method',
        'rule',
        'salutation',
        'shipping_method',
        'tax',
    ];

    return {
        getTypeByName,
        upsertType,
        getTypes,
        getEntityNames,
        addEntityName,
        removeEntityName,
        getCustomFieldSets,
    };

    function getTypeByName(type) {
        return $typeStore[type];
    }

    function upsertType(name, configuration) {
        $typeStore[name] = { ...$typeStore[name], ...{ configuration } };
    }

    function getTypes() {
        return $typeStore;
    }

    function getEntityNames() {
        return $entityNameStore;
    }

    function addEntityName(entityName) {
        $entityNameStore.push(entityName);
    }

    function removeEntityName(entityName) {
        remove($entityNameStore, (storeItem) => { return storeItem === entityName; });
    }

    function getCustomFieldSets(entityName) {
        const customFieldSetRepository = Service('repositoryFactory').create('custom_field_set');

        return customFieldSetRepository.search(customFieldSetCriteria(entityName), Shopware.Context.api).then((sets) => {
            return sets.filter((set) => set.customFields.length > 0);
        });
    }

    function customFieldSetCriteria(entityName) {
        const criteria = new Criteria();

        criteria.addFilter(Criteria.equals('relations.entityName', entityName));
        criteria.getAssociation('customFields').addSorting(Criteria.sort('config.customFieldPosition'));

        return criteria;
    }
}
