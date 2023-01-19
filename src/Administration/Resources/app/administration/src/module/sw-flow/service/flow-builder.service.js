import { ACTION, ACTION_TYPE } from '../constant/flow.constant';

const { Application, EntityDefinition } = Shopware;
const { capitalizeString, snakeCase, camelCase } = Shopware.Utils.string;

Application.addServiceProvider('flowBuilderService', () => {
    return flowBuilderService();
});

/**
 * @private
 * @package business-ops
 */
export default function flowBuilderService() {
    const $icon = {
        addEntityTag: 'regular-tag',
        removeEntityTag: 'regular-tag',
        mailSend: 'regular-envelope',
        grantDownloadAccess: 'regular-file-signature',
        setOrderState: 'regular-shopping-bag-alt',
        generateDocument: 'regular-file-text',
        changeCustomerGroup: 'regular-users',
        changeCustomerStatus: 'regular-user',
        stopFlow: 'regular-times-circle',
        setEntityCustomField: 'regular-file-signature',
        addEntityAffiliateAndCampaignCode: 'regular-file-signature',
    };

    const $labelSnippet = {
        addEntityTag: 'sw-flow.actions.addTag',
        removeEntityTag: 'sw-flow.actions.removeTag',
        mailSend: 'sw-flow.actions.mailSend',
        grantDownloadAccess: 'sw-flow.actions.grantDownloadAccess',
        setOrderState: 'sw-flow.actions.setOrderState',
        generateDocument: 'sw-flow.actions.generateDocument',
        changeCustomerGroup: 'sw-flow.actions.changeCustomerGroup',
        changeCustomerStatus: 'sw-flow.actions.changeCustomerStatus',
        stopFlow: 'sw-flow.actions.stopFlow',
        setEntityCustomField: 'sw-flow.actions.changeCustomFieldContent',
        addEntityAffiliateAndCampaignCode: 'sw-flow.actions.addAffiliateAndCampaignCode',
    };

    const $entityAction = {
        [ACTION.ADD_ORDER_TAG]: 'order',
        [ACTION.ADD_CUSTOMER_TAG]: 'customer',
        [ACTION.REMOVE_ORDER_TAG]: 'order',
        [ACTION.REMOVE_CUSTOMER_TAG]: 'customer',
        [ACTION.SET_ORDER_CUSTOM_FIELD]: 'order',
        [ACTION.SET_CUSTOMER_CUSTOM_FIELD]: 'customer',
        [ACTION.SET_CUSTOMER_GROUP_CUSTOM_FIELD]: 'customer_group',
        [ACTION.ADD_CUSTOMER_AFFILIATE_AND_CAMPAIGN_CODE]: 'customer',
        [ACTION.ADD_ORDER_AFFILIATE_AND_CAMPAIGN_CODE]: 'order',
    };

    return {
        getActionTitle,
        getActionModalName,
        convertEntityName,
        mapActionType,
        getAvailableEntities,
        rearrangeArrayObjects,
    };

    function getActionTitle(actionName) {
        if (!actionName) {
            return null;
        }

        let keyName = '';
        const name = mapActionType(actionName) ?? actionName;

        name.split('.').forEach((key, index) => {
            if (!index) {
                return;
            }

            if (index === 1) {
                keyName = key;
                return;
            }

            keyName += capitalizeString(key);
        });

        return {
            value: actionName,
            icon: $icon[keyName] !== undefined ? $icon[keyName] : $icon.addEntityTag,
            label: $labelSnippet[keyName],
        };
    }

    function getActionModalName(actionName) {
        if (!actionName) {
            return '';
        }

        if (mapActionType(actionName) === ACTION_TYPE.ADD_TAG
            || mapActionType(actionName) === ACTION_TYPE.REMOVE_TAG) {
            return 'sw-flow-tag-modal';
        }

        if (mapActionType(actionName) === ACTION_TYPE.SET_CUSTOM_FIELD) {
            return 'sw-flow-set-entity-custom-field-modal';
        }

        if (mapActionType(actionName) === ACTION_TYPE.ADD_AFFILIATE_AND_CAMPAIGN_CODE) {
            return 'sw-flow-affiliate-and-campaign-code-modal';
        }

        if (mapActionType(actionName) === ACTION_TYPE.GRANT_DOWNLOAD_ACCESS) {
            return 'sw-flow-grant-download-access-modal';
        }

        return `${actionName.replace(/\./g, '-').replace('action', 'sw-flow')}-modal`;
    }

    function mapActionType(actionName) {
        let entity = $entityAction[actionName];

        if (entity === undefined) {
            return null;
        }

        entity = entity.replace('_', '.');

        return actionName.replace(entity, 'entity');
    }

    function getAvailableEntities(selectedAction, actions, allowedAware, entityProperties = []) {
        const availableEntities = [];
        const entities = getEntities(selectedAction, actions, allowedAware);

        entities.forEach((entityName) => {
            if (!EntityDefinition.has(snakeCase(entityName))) {
                return;
            }

            const properties = EntityDefinition.get(snakeCase(entityName)).properties;

            // Check if the entity has the needed properties
            const hasProperties = entityProperties.every(entityProperty => properties.hasOwnProperty(entityProperty));

            if (!hasProperties) {
                return;
            }

            availableEntities.push({
                label: convertEntityName(camelCase(entityName)),
                value: entityName,
            });
        });

        return availableEntities;
    }

    function convertEntityName(camelCaseText) {
        if (!camelCaseText) return '';

        const normalText = camelCaseText.replace(/([A-Z])/g, ' $1');

        return capitalizeString(normalText);
    }

    function getEntities(selectedAction, actions, allowedAware) {
        const entities = [];
        actions.forEach((action) => {
            // Excluding actions which do have different action type with selected action
            if (mapActionType(action.name) === null || mapActionType(action.name) !== mapActionType(selectedAction)) {
                return;
            }

            const isValid = action.requirements.some(aware => allowedAware.includes(aware));
            if (isValid) {
                entities.push($entityAction[action.name]);
            }
        });

        return entities;
    }

    function flattenNodeList(parent, arrayResult) {
        arrayResult.push(parent);

        if (parent.children.length === 0) {
            return;
        }

        parent.children.forEach(child => {
            flattenNodeList(child, arrayResult);
        });
    }

    function rearrangeArrayObjects(items) {
        const itemsKeyMapping = items.reduce((map, item) => {
            map[item.id] = item;
            map[item.id].children = [];

            return map;
        }, {});

        const itemsNodeList = [];
        items.forEach((item) => {
            if (!item.parentId) {
                itemsNodeList.push(item);
            } else {
                const parentNode = itemsKeyMapping[item.parentId];
                parentNode.children.push(item);
            }
        });

        const arrayResult = [];
        itemsNodeList.forEach(node => {
            flattenNodeList(node, arrayResult);
        });

        arrayResult.forEach(item => {
            item.children = [];
            return item;
        });

        return arrayResult;
    }
}
