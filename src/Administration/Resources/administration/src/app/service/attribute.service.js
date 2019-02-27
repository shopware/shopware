/**
 * @module app/service/attribute
 */

/**
 *
 * @memberOf module:core/service/attribute
 * @constructor
 * @method createAttributeTypeService
 * @returns {Object}
 */
export default function createAttributeService() {
    let $typeStore = {
        select: {
            configRenderComponent: 'sw-attribute-type-select',
            type: 'string',
            config: {
                componentName: 'sw-select',
                type: 'select'
            }
        },
        text: {
            configRenderComponent: 'sw-attribute-type-text',
            type: 'text',
            config: {
                componentName: 'sw-field',
                type: 'text'
            }
        },
        media: {
            configRenderComponent: 'sw-attribute-type-media',
            type: 'string',
            config: {
                componentName: 'sw-media-field'
            }
        },
        number: {
            configRenderComponent: 'sw-attribute-type-number',
            type: 'int',
            config: {
                componentName: 'sw-field',
                type: 'number',
                numberType: 'float'
            }
        },
        date: {
            configRenderComponent: 'sw-attribute-type-date',
            type: 'datetime',
            config: {
                componentName: 'sw-field',
                type: 'date',
                dateType: 'datetime'
            }
        },
        checkbox: {
            configRenderComponent: 'sw-attribute-type-checkbox',
            type: 'bool',
            config: {
                componentName: 'sw-field',
                type: 'checkbox'
            }
        },
        switch: {
            configRenderComponent: 'sw-attribute-type-checkbox',
            type: 'bool',
            config: {
                componentName: 'sw-field',
                type: 'switch'
            }
        },
        textEditor: {
            configRenderComponent: 'sw-attribute-type-text-editor',
            type: 'string',
            config: {
                componentName: 'sw-text-editor'
            }
        },
        colorpicker: {
            configRenderComponent: 'sw-attribute-type-colorpicker',
            type: 'string',
            config: {
                componentName: 'sw-field',
                type: 'colorpicker'
            }
        }
    };

    const $entityNameStore = [
        'product',
        'product_manufacturer',
        'customer',
        'customer_address',
        'order',
        'sales_channel',
        'media'
    ];

    return {
        getTypeByName,
        upsertType,
        getTypes,
        getEntityNames,
        addEntityName,
        removeEntityName
    };

    function getTypeByName(type) {
        return $typeStore[type];
    }

    function upsertType(name, configuration) {
        $typeStore = { ...$typeStore, ...{ [name]: configuration } };
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
        if ($entityNameStore.includes(entityName)) {
            $entityNameStore.splice($entityNameStore.indexOf(entityName), 1);
        }
    }
}
