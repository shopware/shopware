import type { Entity } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';
import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { I18n } from 'vue-i18n';
import {
    ACTION,
    ACTION_GROUP,
    GENERAL_GROUP,
    TAG_GROUP,
    CUSTOMER_GROUP,
    ORDER_GROUP,
    ACTION_TYPE,
} from '../constant/flow.constant';

const { Utils, EntityDefinition } = Shopware;
const { capitalizeString, camelCase, snakeCase } = Shopware.Utils.string;

type Node = {
    id: string;
    parentId: string;
    children: Node[];
};

type Primitive = string | number | boolean | null;
type SequenceConfigValues = {
    [key: string]: Primitive | Primitive[] | { [key: string]: Primitive | Primitive[] };
};

type AppAction = Entity<'app_flow_action'> & {
    config: Array<{
        name: string;
        type: string;
        label: { [key: string]: string };
        options: Array<{
            value: string | number;
            label: { [key: string]: string };
        }>;
    }>;
};

type Action = {
    name: string;
    delayable: boolean;
    requirements: string[];
    extensions: never[];
    config: { [key: string]: string };
};

type ActionData = {
    appActions: AppAction[];
    customerGroups: EntityCollection<'customer_group'>;
    customFields: EntityCollection<'custom_field'>;
    customFieldSets: EntityCollection<'custom_field_set'>;
    stateMachineState: EntityCollection<'state_machine_state'>;
    documentTypes: EntityCollection<'document_type'>;
    mailTemplates: EntityCollection<'mail_template'>;
};

type ActionTranslator = {
    // eslint-disable-next-line @typescript-eslint/ban-types
    $tc: I18n<{}, {}, {}, string, true>['global']['tc'];
    currentLocale: string;
    getInlineSnippet(value: { [key: string]: string }): string;
};

type ActionSequence = Entity<'flow_sequence'> & {
    config: SequenceConfigValues & {
        value?: boolean;
        entity?: string;
        active?: boolean;
        order?: string;
        mailTemplateId?: string;
        order_delivery?: string;
        optionLabel?: string;
        customFieldId?: string;
        customFieldSetId?: string;
        customerGroupId?: string;
        order_transaction?: string;
        force_transition?: boolean;
        documentType?: string;
        affiliateCode?: {
            upsert?: boolean;
            value?: string;
        };
        campaignCode?: {
            upsert?: boolean;
            value?: string;
        };
        documentTypes?: Array<{
            documentType: string;
        }>;
    };
};

/**
 * @private
 * @package services-settings
 */
export type ActionContext = {
    data: ActionData;
    sequence: ActionSequence;
    translator: ActionTranslator;
};

/**
 * @private
 * @package services-settings
 */
export default class FlowBuilderService {
    private $actionNames = { ...ACTION };

    private $actionGroupsMapping = { ...ACTION_GROUP };

    private $icon = {
        addEntityTag: 'regular-tag',
        mailSend: 'regular-envelope',
        removeEntityTag: 'regular-tag',
        stopFlow: 'regular-times-circle',
        changeCustomerStatus: 'regular-user',
        changeCustomerGroup: 'regular-users',
        generateDocument: 'regular-file-text',
        setOrderState: 'regular-shopping-bag-alt',
        grantDownloadAccess: 'regular-file-signature',
        setEntityCustomField: 'regular-file-signature',
        addEntityAffiliateAndCampaignCode: 'regular-file-signature',
    };

    private $labelSnippet = {
        stopFlow: 'sw-flow.actions.stopFlow',
        mailSend: 'sw-flow.actions.mailSend',
        addEntityTag: 'sw-flow.actions.addTag',
        removeEntityTag: 'sw-flow.actions.removeTag',
        setOrderState: 'sw-flow.actions.setOrderState',
        generateDocument: 'sw-flow.actions.generateDocument',
        grantDownloadAccess: 'sw-flow.actions.grantDownloadAccess',
        changeCustomerGroup: 'sw-flow.actions.changeCustomerGroup',
        changeCustomerStatus: 'sw-flow.actions.changeCustomerStatus',
        setEntityCustomField: 'sw-flow.actions.changeCustomFieldContent',
        addEntityAffiliateAndCampaignCode: 'sw-flow.actions.addAffiliateAndCampaignCode',
    };

    private $descriptionCallbacks = {
        [this.$actionNames.MAIL_SEND]: (context: ActionContext) => this.getMailSendDescription(context),
        [this.$actionNames.STOP_FLOW]: (context: ActionContext) => this.getStopFlowActionDescription(context),
        [this.$actionNames.SET_ORDER_STATE]: (context: ActionContext) => this.getSetOrderStateDescription(context),
        [this.$actionNames.GENERATE_DOCUMENT]: (context: ActionContext) => this.getGenerateDocumentDescription(context),
        [this.$actionNames.CHANGE_CUSTOMER_GROUP]: (context: ActionContext) => this.getCustomerGroupDescription(context),
        [this.$actionNames.GRANT_DOWNLOAD_ACCESS]: (context: ActionContext) => this.getDownloadAccessDescription(context),
        [this.$actionNames.SET_CUSTOMER_CUSTOM_FIELD]: (context: ActionContext) => this.getCustomFieldDescription(context),
        [this.$actionNames.CHANGE_CUSTOMER_STATUS]: (context: ActionContext) => this.getCustomerStatusDescription(context),
        [this.$actionNames.ADD_ORDER_AFFILIATE_AND_CAMPAIGN_CODE]: (context: ActionContext) =>
            this.getAffiliateAndCampaignCodeDescription(context),
        [this.$actionNames.ADD_CUSTOMER_AFFILIATE_AND_CAMPAIGN_CODE]: (context: ActionContext) =>
            this.getAffiliateAndCampaignCodeDescription(context),
    };

    private $entityAction = {
        [this.$actionNames.ADD_ORDER_TAG]: 'order',
        [this.$actionNames.REMOVE_ORDER_TAG]: 'order',
        [this.$actionNames.ADD_CUSTOMER_TAG]: 'customer',
        [this.$actionNames.SET_ORDER_CUSTOM_FIELD]: 'order',
        [this.$actionNames.REMOVE_CUSTOMER_TAG]: 'customer',
        [this.$actionNames.SET_CUSTOMER_CUSTOM_FIELD]: 'customer',
        [this.$actionNames.ADD_ORDER_AFFILIATE_AND_CAMPAIGN_CODE]: 'order',
        [this.$actionNames.SET_CUSTOMER_GROUP_CUSTOM_FIELD]: 'customer_group',
        [this.$actionNames.ADD_CUSTOMER_AFFILIATE_AND_CAMPAIGN_CODE]: 'customer',
    };

    private $groups = {
        TAG: TAG_GROUP,
        ORDER: ORDER_GROUP,
        GENERAL: GENERAL_GROUP,
        CUSTOMER: CUSTOMER_GROUP,
    };

    public addDescriptionCallbacks(callback: (context: ActionContext) => string) {
        Object.assign(this.$descriptionCallbacks, callback);
    }

    public getDescriptionCallbacks() {
        return this.$descriptionCallbacks;
    }

    public addIcons(icons: string) {
        return Object.assign(this.$icon, icons);
    }

    public addLabels(labels: { [key: string]: string }) {
        return Object.assign(this.$labelSnippet, labels);
    }

    public getActionName(key: keyof typeof this.$actionNames) {
        return this.$actionNames[key];
    }

    public addActionNames(actions: { [key: string]: string }) {
        return Object.assign(this.$actionNames, actions);
    }

    public addGroups(groups: { [key: string]: string }) {
        return Object.assign(this.$groups, groups);
    }

    public getGroup(key: keyof typeof this.$groups) {
        return this.$groups[key];
    }

    public getGroups(): string[] {
        return Object.values(this.$groups);
    }

    public getActionGroupMapping(key: keyof typeof this.$actionGroupsMapping) {
        return this.$actionGroupsMapping[key];
    }

    public addActionGroupMapping(actionGroup: { [key: string]: string }) {
        return Object.assign(this.$actionGroupsMapping, actionGroup);
    }

    public isKeyOfActionName(key: string): key is keyof typeof this.$entityAction {
        return key in this.$entityAction;
    }

    public isKeyOfActionLabel(key: string): key is keyof typeof this.$labelSnippet {
        return key in this.$labelSnippet;
    }

    public isKeyOfActionDescription(key: string): key is keyof typeof this.$descriptionCallbacks {
        return key in this.$descriptionCallbacks;
    }

    public isKeyOfEntityIcon(key: string): key is keyof typeof this.$icon {
        return key in this.$icon;
    }

    public getActionTitle(actionName: keyof typeof this.$entityAction) {
        if (!actionName) {
            return null;
        }

        let keyName = '';
        const name = this.mapActionType(actionName) ?? actionName;

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
            icon: this.isKeyOfEntityIcon(keyName) ? this.$icon[keyName] : 'regular-question-circle-s',
            label: this.isKeyOfActionLabel(keyName) ? this.$labelSnippet[keyName] : 'sw-flow.actions.unknownLabel',
        };
    }

    public mapActionType(actionName: keyof typeof this.$entityAction) {
        let entity = this.$entityAction[actionName];

        if (entity === undefined) {
            return null;
        }

        entity = entity.replace('_', '.');

        return actionName.replace(entity, 'entity');
    }

    public getDescription(format: { [key: string]: string }) {
        const description: string[] = [];

        Object.entries(format).forEach(
            ([
                key,
                value,
            ]) => {
                let label = value;

                if (Utils.types.isPlainObject(value)) {
                    label = Object.values(value).join(', ');
                }

                const text = `<span>${key}:</span> <span>${label}</span></br>`;
                description.push(`<p class="${key.toLowerCase().replace(/ /g, '_')}">${text}</p>`);
            },
        );

        return description.join('');
    }

    public convertConfig(config: ActionSequence['config'], translator: ActionTranslator) {
        const description = {};
        const entries = Object.entries(config);

        entries.forEach(
            ([
                key,
                value,
            ]) => {
                if (!this.isKeyOfActionLabel(key)) {
                    return;
                }

                const snippet = translator.$tc(this.$labelSnippet[key]);

                if (!snippet) {
                    return;
                }

                Object.assign(description, {
                    [snippet]: value,
                });
            },
        );

        return description;
    }

    public getActionDescriptions(data: ActionData, sequence: ActionSequence, translator: ActionTranslator) {
        const context: ActionContext = { data, sequence, translator };

        if (!sequence.actionName) {
            return '';
        }

        const selectedAppAction = data.appActions?.find((item) => item.name === sequence.actionName);

        if (selectedAppAction) {
            return this.getAppFlowActionDescription(context);
        }

        if (
            this.isKeyOfActionDescription(sequence.actionName) &&
            typeof this.$descriptionCallbacks[sequence.actionName] === 'function'
        ) {
            return this.$descriptionCallbacks[sequence.actionName](context);
        }

        const convertedDescription = this.convertConfig(sequence.config, translator);

        return this.getDescription(convertedDescription);
    }

    public getAppFlowActionDescription(context: ActionContext) {
        const {
            sequence: { config },
        } = context;

        const cloneConfig = { ...config } as SequenceConfigValues;
        let descriptions = '';

        Object.entries(cloneConfig).forEach(
            ([
                fieldName,
                fieldValue,
            ]) => {
                if (Array.isArray(fieldValue) && fieldValue.length > 1) {
                    let html = '';

                    fieldValue.forEach((val) => {
                        const valPreview = this.formatValuePreview(context, fieldName, val);
                        html = `${html}- ${valPreview.toString()}<br/>`;
                    });

                    descriptions = `${descriptions}${this.convertLabelPreview(context, fieldName)}:<br/> ${html}`;
                } else {
                    const valPreview = this.formatValuePreview(context, fieldName, fieldValue);
                    // eslint-disable-next-line max-len
                    descriptions = `${descriptions}${this.convertLabelPreview(context, fieldName)}: ${valPreview.toString()}<br/>`;
                }
            },
        );

        return descriptions;
    }

    public formatValuePreview(
        context: ActionContext,
        fieldName: string,
        val: SequenceConfigValues[keyof SequenceConfigValues],
    ) {
        const {
            data: { appActions },
            sequence: { actionName },
        } = context;

        const value: string = this.configValuesToString(val);
        const selectedAppAction = appActions.find((item) => item.name === actionName);

        if (selectedAppAction === undefined) {
            return value;
        }

        const config = selectedAppAction.config?.find((field) => field.name === fieldName);
        if (config === undefined) {
            return value;
        }

        if (['password'].includes(config.type)) {
            return value?.replace(/([^;])/g, '*');
        }

        if (
            [
                'single-select',
                'multi-select',
            ].includes(config.type)
        ) {
            const option = config.options.find((opt) => opt.value === value);

            if (option === undefined) {
                return value;
            }

            return option.label[context.translator.currentLocale] ?? config.label['en-GB'] ?? value;
        }

        if (
            [
                'datetime',
                'date',
                'time',
            ].includes(config.type)
        ) {
            return new Date(value);
        }

        if (['colorpicker'].includes(config.type)) {
            return `<span class="sw-color-badge is--default" style="background: ${value};"></span> ${value}`;
        }

        return value;
    }

    public convertLabelPreview(context: ActionContext, fieldName: string) {
        const {
            data: { appActions },
            sequence: { actionName },
        } = context;

        const selectedAppAction = appActions.find((item) => item.name === actionName);

        if (selectedAppAction === undefined) {
            return fieldName;
        }

        const config = selectedAppAction.config?.find((field) => field.name === fieldName);
        if (config === undefined) {
            return fieldName;
        }

        return config.label[context.translator.currentLocale] ?? config.label['en-GB'] ?? fieldName;
    }

    public getActionModalName(actionName: keyof typeof this.$entityAction) {
        if (!actionName) {
            return '';
        }

        if (
            this.mapActionType(actionName) === ACTION_TYPE.ADD_TAG ||
            this.mapActionType(actionName) === ACTION_TYPE.REMOVE_TAG
        ) {
            return 'sw-flow-tag-modal';
        }

        if (this.mapActionType(actionName) === ACTION_TYPE.SET_CUSTOM_FIELD) {
            return 'sw-flow-set-entity-custom-field-modal';
        }

        if (this.mapActionType(actionName) === ACTION_TYPE.ADD_AFFILIATE_AND_CAMPAIGN_CODE) {
            return 'sw-flow-affiliate-and-campaign-code-modal';
        }

        if (this.mapActionType(actionName) === ACTION.GRANT_DOWNLOAD_ACCESS) {
            return 'sw-flow-grant-download-access-modal';
        }

        return `${actionName.replace(/\./g, '-').replace('action', 'sw-flow')}-modal`;
    }

    public getStopFlowActionDescription(context: ActionContext) {
        return context.translator.$tc('sw-flow.actions.textStopFlowDescription');
    }

    public getCustomerStatusDescription(context: ActionContext) {
        const {
            sequence: { config },
            translator,
        } = context;

        return config.active
            ? translator.$tc('sw-flow.modals.customerStatus.active')
            : translator.$tc('sw-flow.modals.customerStatus.inactive');
    }

    public getAffiliateAndCampaignCodeDescription(context: ActionContext) {
        const {
            translator,
            sequence: { config },
        } = context;

        let description = translator.$tc('sw-flow.actions.labelTo', 0, {
            entity: capitalizeString(config?.entity),
        });

        if (config?.affiliateCode?.upsert || config?.affiliateCode?.value != null) {
            description = `${description}<br>${translator.$tc('sw-flow.actions.labelAffiliateCode', 0, {
                affiliateCode: config.affiliateCode.value || '',
            })}`;
        }

        if (config.campaignCode?.upsert || config?.campaignCode?.value != null) {
            description = `${description}<br>${translator.$tc('sw-flow.actions.labelCampaignCode', 0, {
                campaignCode: config?.campaignCode?.value || '',
            })}`;
        }

        return description;
    }

    public getCustomerGroupDescription(context: ActionContext) {
        const {
            data,
            sequence: { config },
        } = context;

        const customerGroup = data.customerGroups.find((item) => item.id === config.customerGroupId);
        return customerGroup?.translated?.name;
    }

    public getCustomFieldDescription(context: ActionContext) {
        const {
            data: { customFieldSets, customFields },
            sequence: { config },
            translator,
        } = context;

        const customFieldSet = customFieldSets.find((item) => item.id === config.customFieldSetId);
        const customField = customFields.find((item) => item.id === config.customFieldId) as Entity<'custom_field'> & {
            config?: { label?: { [key: string]: string } };
        };

        if (!customFieldSet || !customField) {
            return '';
        }

        if (typeof customField.config !== 'object' || !customField.config) {
            return '';
        }

        if (!customField.config.hasOwnProperty('label')) {
            return '';
        }

        if (!customField.config.label) {
            return '';
        }

        return `${translator.$tc('sw-flow.actions.labelCustomFieldSet', 0, {
            customFieldSet: translator.getInlineSnippet(customField.config.label) || customFieldSet.name,
        })}<br>${translator.$tc('sw-flow.actions.labelCustomField', 0, {
            customField: translator.getInlineSnippet(customField.config.label) || customField.name,
        })}<br>${translator.$tc('sw-flow.actions.labelCustomFieldOption', 0, {
            customFieldOption: config.optionLabel,
        })}`;
    }

    public getSetOrderStateDescription(context: ActionContext) {
        const {
            data: { stateMachineState },
            sequence: { config },
            translator,
        } = context;

        const description = [];
        if (config.order) {
            const orderStatus = stateMachineState.find(
                (item) => item.technicalName === config.order && item.stateMachine?.technicalName === 'order.state',
            );
            const orderStatusName = orderStatus?.translated?.name || '';
            description.push(`${translator.$tc('sw-flow.modals.status.labelOrderStatus')}: ${orderStatusName}`);
        }

        if (config.order_delivery) {
            const deliveryStatus = stateMachineState.find(
                (item) =>
                    item.technicalName === config.order_delivery &&
                    item.stateMachine?.technicalName === 'order_delivery.state',
            );
            const deliveryStatusName = deliveryStatus?.translated?.name || '';
            description.push(`
                ${translator.$tc('sw-flow.modals.status.labelDeliveryStatus')}: ${deliveryStatusName}
            `);
        }

        if (config.order_transaction) {
            const paymentStatus = stateMachineState.find(
                (item) =>
                    item.technicalName === config.order_transaction &&
                    item.stateMachine?.technicalName === 'order_transaction.state',
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

    public getGenerateDocumentDescription(context: ActionContext) {
        const {
            sequence: { config },
            data,
        } = context;

        if (config.documentType) {
            Object.assign(config, {
                documentType: [config],
            });
        }

        const documentType = config.documentTypes?.map((type) => {
            return data.documentTypes.find((item) => item.technicalName === type.documentType)?.translated?.name || '';
        });

        if (!documentType) {
            return '';
        }

        return this.convertTagString(documentType);
    }

    public convertTagString(tagsString: string[]) {
        return tagsString.toString().replace(/,/g, ', ');
    }

    public getMailSendDescription(context: ActionContext) {
        const {
            data,
            sequence: { config },
            translator,
        } = context;

        const mailTemplateData = data.mailTemplates.find((item) => item.id === config.mailTemplateId);

        let mailSendDescription = translator.$tc('sw-flow.actions.labelTemplate', 0, {
            template: mailTemplateData?.mailTemplateType?.name,
        });

        let mailDescription = mailTemplateData?.description;

        if (mailDescription) {
            // Truncate description string
            mailDescription = mailDescription.length > 60 ? `${mailDescription.substring(0, 60)}...` : mailDescription;

            mailSendDescription = `${mailSendDescription}<br>${translator.$tc('sw-flow.actions.labelDescription', 0, {
                description: mailDescription,
            })}`;
        }

        return mailSendDescription;
    }

    public getDownloadAccessDescription(context: ActionContext) {
        const {
            sequence: { config },
            translator,
        } = context;

        return config.value
            ? translator.$tc('sw-flow.actions.downloadAccessLabel.granted')
            : translator.$tc('sw-flow.actions.downloadAccessLabel.revoked');
    }

    public getAvailableEntities(
        selectedAction: keyof typeof this.$entityAction,
        actions: Action[],
        allowedAware: string[],
        entityProperties: string[] = [],
    ) {
        const availableEntities: { label: string; value: string }[] = [];
        const entities = this.getEntities(selectedAction, actions, allowedAware);

        entities.forEach((entityName) => {
            if (!EntityDefinition.has(snakeCase(entityName))) {
                return;
            }

            const properties = EntityDefinition.get(snakeCase(entityName)).properties;

            // Check if the entity has the needed properties
            const hasProperties = entityProperties.every((entityProperty) => properties.hasOwnProperty(entityProperty));

            if (!hasProperties) {
                return;
            }

            availableEntities.push({
                label: this.convertEntityName(camelCase(entityName)),
                value: entityName,
            });
        });

        return availableEntities;
    }

    public convertEntityName(camelCaseText: string) {
        if (!camelCaseText) {
            return '';
        }

        const normalText = camelCaseText.replace(/([A-Z])/g, ' $1');

        return capitalizeString(normalText);
    }

    public getEntities(selectedAction: keyof typeof this.$entityAction, actions: Action[], allowedAware: string[]) {
        const entities: string[] = [];

        actions.forEach((action) => {
            if (!this.isKeyOfActionName(action.name)) {
                return;
            }

            // Excluding actions which do have different action type with selected action
            if (
                this.mapActionType(action.name) === null ||
                this.mapActionType(action.name) !== this.mapActionType(selectedAction)
            ) {
                return;
            }

            const isValid = action.requirements.some((aware) => allowedAware.includes(aware));
            if (isValid) {
                entities.push(this.$entityAction[action.name]);
            }
        });

        return entities;
    }

    public flattenNodeList(parent: Node, arrayResult: Node[]) {
        arrayResult.push(parent);

        if (parent.children.length === 0) {
            return;
        }

        parent.children.forEach((child) => {
            this.flattenNodeList(child, arrayResult);
        });
    }

    public configValuesToString(val: SequenceConfigValues[keyof SequenceConfigValues]): string {
        if (val === null) {
            return 'null';
        }

        if (typeof val === 'string' || typeof val === 'number' || typeof val === 'boolean') {
            return val.toString();
        }

        if (Array.isArray(val)) {
            return `[${val.map((item) => this.configValuesToString(item)).join(', ')}]`;
        }

        if (typeof val === 'object') {
            return `{${Object.keys(val)
                .map((key) => `${key}: ${this.configValuesToString(val[key])}`)
                .join(', ')}}`;
        }

        return '';
    }

    public rearrangeArrayObjects(items: Node[]) {
        const itemsKeyMapping = items.reduce(
            (map, item) => {
                map[item.id] = item;
                map[item.id].children = [];

                return map;
            },
            {} as { [key: string]: Node },
        );

        const itemsNodeList: Node[] = [];

        items.forEach((item) => {
            if (!item.parentId) {
                itemsNodeList.push(item);
            } else {
                const parentNode = itemsKeyMapping[item.parentId];
                parentNode.children.push(item);
            }
        });

        const arrayResult: Node[] = [];
        itemsNodeList.forEach((node) => {
            this.flattenNodeList(node, arrayResult);
        });

        arrayResult.forEach((item) => {
            item.children = [];
            return item;
        });

        return arrayResult;
    }
}
