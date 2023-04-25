import {
    ACTION_TYPE,
    ACTION,
    ACTION_GROUP,
    GENERAL_GROUP,
    TAG_GROUP,
    CUSTOMER_GROUP,
    ORDER_GROUP,
} from '../constant/flow.constant';

const { Application, EntityDefinition, Utils } = Shopware;
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

    const $actionNames = { ...ACTION };

    const $groups = {
        GENERAL: GENERAL_GROUP,
        TAG: TAG_GROUP,
        CUSTOMER: CUSTOMER_GROUP,
        ORDER: ORDER_GROUP,
    };

    const $actionGroupsMapping = { ...ACTION_GROUP };

    const $entityAction = {
        [$actionNames.ADD_ORDER_TAG]: 'order',
        [$actionNames.ADD_CUSTOMER_TAG]: 'customer',
        [$actionNames.REMOVE_ORDER_TAG]: 'order',
        [$actionNames.REMOVE_CUSTOMER_TAG]: 'customer',
        [$actionNames.SET_ORDER_CUSTOM_FIELD]: 'order',
        [$actionNames.SET_CUSTOMER_CUSTOM_FIELD]: 'customer',
        [$actionNames.SET_CUSTOMER_GROUP_CUSTOM_FIELD]: 'customer_group',
        [$actionNames.ADD_CUSTOMER_AFFILIATE_AND_CAMPAIGN_CODE]: 'customer',
        [$actionNames.ADD_ORDER_AFFILIATE_AND_CAMPAIGN_CODE]: 'order',
    };

    return {
        getActionTitle,
        getActionModalName,
        convertEntityName,
        mapActionType,
        getAvailableEntities,
        rearrangeArrayObjects,
        getDescription,
        getActionDescriptions,
        getCustomerStatusDescription,
        getAffiliateAndCampaignCodeDescription,
        getCustomerGroupDescription,
        getCustomFieldDescription,
        getSetOrderStateDescription,
        convertTagString,
        getGenerateDocumentDescription,
        getMailSendDescription,
        getDownloadAccessDescription,
        convertConfig,
        getAppFlowActionDescription,
        formatValuePreview,
        convertLabelPreview,
        getActionName,
        addActionNames,
        addIcons,
        addLabels,
        getActionGroupMapping,
        addActionGroupMapping,
        getGroup,
        addGroups,
        getGroups,
    };

    function addIcons(icons) {
        return Object.assign($icon, icons);
    }

    function addLabels(labels) {
        return Object.assign($labelSnippet, labels);
    }

    function addActionNames(actions) {
        return Object.assign($actionNames, actions);
    }

    function addGroups(groups) {
        return Object.assign($groups, groups);
    }

    function addActionGroupMapping(actionGroup) {
        return Object.assign($actionGroupsMapping, actionGroup);
    }

    function getActionName(key) {
        return $actionNames[key];
    }

    function getActionGroupMapping(key) {
        return $actionGroupsMapping[key];
    }

    function getGroup(key) {
        return $groups[key];
    }

    function getGroups() {
        return Object.values($groups);
    }

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
            icon: $icon[keyName] || 'regular-question-circle-s',
            label: $labelSnippet[keyName] || 'sw-flow.actions.unknownLabel',
        };
    }

    function getDescription(format) {
        const description = [];

        Object.entries(format).forEach(([key, value]) => {
            let label = value;
            if (Utils.types.isPlainObject(value)) {
                label = Object.values(value).join(', ');
            }

            const text = `<span>${key}:</span> <span>${label}</span></br>`;
            description.push(`<p class="${key.toLowerCase().replace(/ /g, '_')}">${text}</p>`);
        });

        return description.join('');
    }

    function convertConfig(config, translator) {
        const description = {};
        const entries = Object.entries(config);

        entries.forEach(([key, value]) => {
            const snippet = translator.$tc($labelSnippet[key]);
            if (!snippet) {
                return;
            }

            Object.assign(description, {
                [snippet]: value,
            });
        });

        return description;
    }

    function getActionDescriptions(data, sequence, translator) {
        const { actionName, config } = sequence;
        const {
            appActions,
            customerGroups,
            customFieldSets,
            customFields,
            stateMachineState,
            documentTypes,
            mailTemplates,
        } = data;

        if (!actionName) return '';

        const selectedAppAction = appActions.find(item => item.name === actionName);
        if (selectedAppAction) {
            return this.getAppFlowActionDescription(appActions, config, actionName);
        }

        switch (actionName) {
            case $actionNames.STOP_FLOW:
                return translator.$tc('sw-flow.actions.textStopFlowDescription');

            case $actionNames.CHANGE_CUSTOMER_STATUS:
                return this.getCustomerStatusDescription(config, translator);

            case $actionNames.ADD_CUSTOMER_AFFILIATE_AND_CAMPAIGN_CODE:
            case $actionNames.ADD_ORDER_AFFILIATE_AND_CAMPAIGN_CODE:
                return this.getAffiliateAndCampaignCodeDescription(config, translator);

            case $actionNames.CHANGE_CUSTOMER_GROUP:
                return this.getCustomerGroupDescription(customerGroups, config);

            case $actionNames.SET_CUSTOMER_CUSTOM_FIELD:
                return this.getCustomFieldDescription(customFieldSets, customFields, config, translator);

            case $actionNames.SET_ORDER_STATE:
                return this.getSetOrderStateDescription(stateMachineState, config, translator);

            case $actionNames.GENERATE_DOCUMENT:
                return this.getGenerateDocumentDescription(documentTypes, config);

            case $actionNames.MAIL_SEND:
                return this.getMailSendDescription(mailTemplates, config, translator);

            case $actionNames.GRANT_DOWNLOAD_ACCESS:
                return this.getDownloadAccessDescription(config, translator);

            default: {
                const convertedDescription = this.convertConfig(config, translator);
                return this.getDescription(convertedDescription);
            }
        }
    }

    function getAppFlowActionDescription(appActions, config, actionName) {
        const cloneConfig = { ...config };
        let descriptions = '';

        Object.entries(cloneConfig).forEach(([fieldName]) => {
            if (typeof cloneConfig[fieldName] === 'object' && cloneConfig[fieldName].length > 1) {
                let html = '';
                cloneConfig[fieldName].forEach((val) => {
                    const valPreview = this.formatValuePreview(appActions, fieldName, actionName, val);
                    html = `${html}- ${valPreview}<br/>`;
                });

                descriptions = `${descriptions}${this.convertLabelPreview(appActions, fieldName, actionName)}:<br/> ${html}`;

                return;
            }

            const valPreview = this.formatValuePreview(appActions, fieldName, actionName, cloneConfig[fieldName]);
            descriptions = `
                ${descriptions}${this.convertLabelPreview(appActions, fieldName, actionName)}: ${valPreview}<br/>
            `;
        });

        return descriptions;
    }

    function formatValuePreview(appActions, fieldName, actionName, val) {
        const selectedAppAction = appActions.find(item => item.name === actionName);
        if (selectedAppAction === undefined) {
            return val;
        }

        const config = selectedAppAction.config?.find((field) => field.name === fieldName);
        if (config === undefined) {
            return val;
        }

        if (['password'].includes(config.type)) {
            return val.replace(/([^;])/g, '*');
        }

        if (['single-select', 'multi-select'].includes(config.type)) {
            const value = typeof val === 'string' ? val : val[0];
            const option = config.options.find((opt) => opt.value === value);
            if (option === undefined) {
                return val;
            }

            return option.label[this.currentLocale] ?? config.label['en-GB'] ?? val;
        }

        if (['datetime', 'date', 'time'].includes(config.type)) {
            return new Date(val);
        }

        if (['colorpicker'].includes(config.type)) {
            return `<span class="sw-color-badge is--default" style="background: ${val};"></span> ${val}`;
        }

        return val;
    }

    function convertLabelPreview(appActions, fieldName, actionName) {
        const selectedAppAction = appActions.find(item => item.name === actionName);
        if (selectedAppAction === undefined) {
            return fieldName;
        }

        const config = selectedAppAction.config?.find((field) => field.name === fieldName);
        if (config === undefined) {
            return fieldName;
        }

        return config.label[this.currentLocale] ?? config.label['en-GB'] ?? fieldName;
    }

    function getCustomerStatusDescription(config, translator) {
        return config.active
            ? translator.$tc('sw-flow.modals.customerStatus.active')
            : translator.$tc('sw-flow.modals.customerStatus.inactive');
    }

    function getAffiliateAndCampaignCodeDescription(config, translator) {
        let description = translator.$tc('sw-flow.actions.labelTo', 0, {
            entity: capitalizeString(config.entity),
        });

        if (config.affiliateCode.upsert || config.affiliateCode.value != null) {
            description = `${description}<br>${translator.$tc('sw-flow.actions.labelAffiliateCode', 0, {
                affiliateCode: config.affiliateCode.value || '',
            })}`;
        }

        if (config.campaignCode.upsert || config.campaignCode.value != null) {
            description = `${description}<br>${translator.$tc('sw-flow.actions.labelCampaignCode', 0, {
                campaignCode: config.campaignCode.value || '',
            })}`;
        }

        return description;
    }

    function getCustomerGroupDescription(customerGroups, config) {
        const customerGroup = customerGroups.find(item => item.id === config.customerGroupId);
        return customerGroup?.translated?.name;
    }

    function getCustomFieldDescription(customFieldSets, customFields, config, translator) {
        const customFieldSet = customFieldSets.find(item => item.id === config.customFieldSetId);
        const customField = customFields.find(item => item.id === config.customFieldId);
        if (!customFieldSet || !customField) {
            return '';
        }

        return `${translator.$tc('sw-flow.actions.labelCustomFieldSet', 0, {
            customFieldSet: translator.getInlineSnippet(customFieldSet.config.label) || customFieldSet.name,
        })}<br>${translator.$tc('sw-flow.actions.labelCustomField', 0, {
            customField: translator.getInlineSnippet(customField.config.label) || customField.name,
        })}<br>${translator.$tc('sw-flow.actions.labelCustomFieldOption', 0, {
            customFieldOption: config.optionLabel,
        })}`;
    }

    function getSetOrderStateDescription(stateMachineState, config, translator) {
        const description = [];
        if (config.order) {
            const orderStatus = stateMachineState.find(item => item.technicalName === config.order
                && item.stateMachine.technicalName === 'order.state');
            const orderStatusName = orderStatus?.translated?.name || '';
            description.push(`${translator.$tc('sw-flow.modals.status.labelOrderStatus')}: ${orderStatusName}`);
        }

        if (config.order_delivery) {
            const deliveryStatus = stateMachineState.find(
                item => item.technicalName === config.order_delivery
                    && item.stateMachine.technicalName === 'order_delivery.state',
            );
            const deliveryStatusName = deliveryStatus?.translated?.name || '';
            description.push(`
                ${translator.$tc('sw-flow.modals.status.labelDeliveryStatus')}: ${deliveryStatusName}
            `);
        }

        if (config.order_transaction) {
            const paymentStatus = stateMachineState.find(
                item => item.technicalName === config.order_transaction
                    && item.stateMachine.technicalName === 'order_transaction.state',
            );
            const paymentStatusName = paymentStatus?.translated?.name || '';
            description.push(`${translator.$tc('sw-flow.modals.status.labelPaymentStatus')}: ${paymentStatusName}`);
        }

        const forceTransition = config.force_transition
            ? translator.$tc('global.default.yes')
            : translator.$tc('global.default.no');

        description.push(`${translator.$tc('sw-flow.modals.status.forceTransition')}: ${forceTransition}`);

        return description.join('<br>');
    }

    function getGenerateDocumentDescription(documentTypes, config) {
        if (config.documentType) {
            config = {
                documentTypes: [config],
            };
        }

        const documentType = config.documentTypes.map((type) => {
            return documentTypes.find(
                item => item.technicalName === type.documentType,
            )?.translated?.name;
        });

        return this.convertTagString(documentType);
    }

    function getMailSendDescription(mailTemplates, config, translator) {
        const mailTemplateData = mailTemplates.find(item => item.id === config.mailTemplateId);

        let mailSendDescription = translator.$tc('sw-flow.actions.labelTemplate', 0, {
            template: mailTemplateData?.mailTemplateType?.name,
        });

        let mailDescription = mailTemplateData?.description;

        if (mailDescription) {
            // Truncate description string
            mailDescription = mailDescription.length > 60
                ? `${mailDescription.substring(0, 60)}...`
                : mailDescription;

            mailSendDescription = `${mailSendDescription}<br>${translator.$tc('sw-flow.actions.labelDescription', 0, {
                description: mailDescription,
            })}`;
        }

        return mailSendDescription;
    }

    function getDownloadAccessDescription(config, translator) {
        return config.value
            ? translator.$tc('sw-flow.actions.downloadAccessLabel.granted')
            : translator.$tc('sw-flow.actions.downloadAccessLabel.revoked');
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

    function convertTagString(tagsString) {
        return tagsString.toString().replace(/,/g, ', ');
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
