/**
 * @sw-package framework
 */
import ErrorResolverSystemConfig from 'src/core/data/error-resolver.system-config.data';
import { deepCloneWithEntity } from 'src/core/service/extension-api-data.service';
import { supportsMapInheritance } from 'src/core/service/utils/field-inheritance.utils';
import { isMeteorComponent as isMeteorFieldComponent } from 'src/core/service/utils/meteor-component.utils';
import { isFieldHandlingLabelAndHelpText as fieldHandlesLabelAndHelpText } from 'src/core/service/utils/field-label.utils';
import template from './sw-system-config.html.twig';
import './sw-system-config.scss';

const { Mixin } = Shopware;
const {
    object,
    string: { kebabCase },
} = Shopware.Utils;
const { mapSystemConfigErrors } = Shopware.Component.getComponentHelper();

/**
 * Component which automatically renders all fields for a given system_config schema. It allows the user to edit these
 * configuration values.
 *
 * N.B: This component handles the data completely independently, therefore you need to trigger the saving of
 *      data manually with a $ref. Due to the fact that the data is stored inside this component, destroying
 *      the component could lead to unsaved changes. One primary case for this could be if it will be used
 *      inside tabs. Because if the user changes the tab content then this component gets destroyed and therefore
 *      also the corresponding data.
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['systemConfigApiService'],

    emits: [
        'loading-changed',
        'config-changed',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet'),
    ],

    props: {
        domain: {
            required: true,
            type: String,
        },
        salesChannelId: {
            required: false,
            type: String,
            default: null,
        },
        salesChannelSwitchable: {
            type: Boolean,
            required: false,
            default: false,
        },
        // Shows the value of salesChannel=null as placeholder when the salesChannelSwitchable prop is true
        inherit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            currentSalesChannelId: this.salesChannelId,
            isLoading: false,
            config: {},
            actualConfigData: {},
            salesChannelModel: null,
            hasCssFields: false,
        };
    },

    computed: {
        isNotDefaultSalesChannel() {
            return this.currentSalesChannelId !== null;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, use `Shopware.Utils.supportsMapInheritance` to test for map inheritance support instead.
         */
        typesWithMapInheritanceSupport() {
            return [
                'text',
                'textarea',
                'url',
                'password',
                'int',
                'float',
                'checkbox',
                'colorpicker',
            ];
        },
    },

    watch: {
        actualConfigData: {
            handler() {
                this.emitConfig();
            },
            deep: true,
        },

        domain: {
            handler() {
                this.createdComponent();
            },
        },

        isLoading(value) {
            this.$emit('loading-changed', value);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        getFieldError(fieldName) {
            return mapSystemConfigErrors(ErrorResolverSystemConfig.ENTITY_NAME, this.salesChannelId, fieldName);
        },

        async createdComponent() {
            this.isLoading = true;
            try {
                await this.readConfig();
                await this.readAll();
            } catch (error) {
                if (error?.response?.data?.errors) {
                    this.createErrorNotification(error.response.data.errors);
                }
            } finally {
                this.isLoading = false;
            }
        },

        async readConfig() {
            this.config = await this.systemConfigApiService.getConfig(this.domain);
            this.config.every((card) => {
                return card?.elements.every((field) => {
                    if (field?.config?.css) {
                        this.hasCssFields = true;
                        return false;
                    }
                    return true;
                });
            });
        },

        readAll() {
            this.isLoading = true;
            // Return when data for this salesChannel was already loaded
            if (this.actualConfigData.hasOwnProperty(this.currentSalesChannelId)) {
                this.isLoading = false;
                return Promise.resolve();
            }

            return this.loadCurrentSalesChannelConfig();
        },

        async loadCurrentSalesChannelConfig() {
            this.isLoading = true;

            try {
                const values = await this.systemConfigApiService.getValues(this.domain, this.currentSalesChannelId);

                this.actualConfigData[this.currentSalesChannelId] = values;
            } finally {
                this.isLoading = false;
            }
        },

        saveAll() {
            this.isLoading = true;
            return this.systemConfigApiService.batchSave(this.actualConfigData).finally(() => {
                this.isLoading = false;
            });
        },

        createErrorNotification(errors) {
            let message = `<div>${this.$t('sw-config-form-renderer.configLoadErrorMessage', {}, errors.length)}</div><ul>`;

            errors.forEach((error) => {
                message = `${message}<li>${error.detail}</li>`;
            });
            message += '</ul>';

            this.createNotificationError({
                message: message,
                autoClose: false,
            });
        },

        onSalesChannelChanged(salesChannelId) {
            this.currentSalesChannelId = salesChannelId;
            this.readAll();
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, use `Shopware.Utils.supportsMapInheritance` instead.
         */
        hasMapInheritanceSupport(element) {
            return supportsMapInheritance(element);
        },

        getElementBind(element, mapInheritance) {
            const bind = object.deepCopyObject(element);

            if (!supportsMapInheritance(element)) {
                delete bind.config.label;
                delete bind.config.helpText;
            } else {
                bind.mapInheritance = mapInheritance;
            }

            // Add select properties
            if (
                [
                    'single-select',
                    'multi-select',
                ].includes(bind.type)
            ) {
                bind.config.labelProperty = 'name';
                bind.config.valueProperty = 'id';

                if (bind.config.required) {
                    bind.config.hideClearableButton = true;
                }
            }

            if (element.type === 'text-editor') {
                bind.config.componentName = 'sw-text-editor';
            }

            if (bind.config.css && bind.config.helpText === undefined) {
                bind.config.helpText = this.$t('sw-settings.system-config.scssHelpText') + element.config.css;
            }

            return bind;
        },

        getInheritWrapperBind(element) {
            if (fieldHandlesLabelAndHelpText(element, { renderedByFormFieldRenderer: true })) {
                return {};
            }

            return {
                label: this.getInlineSnippet(element.config.label),
                helpText: this.getInlineSnippet(element.config.helpText),
            };
        },

        getInheritedValue(element) {
            let value = this.actualConfigData.null[element.name];

            if (typeof value === 'object' && !Array.isArray(value) && value !== null) {
                value = deepCloneWithEntity(value);
            }

            if (value) {
                return value;
            }

            if (element.config?.componentName) {
                const componentName = element.config.componentName;

                if (componentName === 'sw-switch-field') {
                    return false;
                }
            }

            switch (element.type) {
                case 'date':
                case 'datetime':
                case 'single-select':
                case 'colorpicker':
                case 'password':
                case 'url':
                case 'text':
                case 'textarea':
                case 'text-editor': {
                    return '';
                }

                case 'multi-select': {
                    return [];
                }

                case 'checkbox':
                case 'bool': {
                    return false;
                }

                case 'float':
                case 'int': {
                    return 0;
                }

                default: {
                    return null;
                }
            }
        },

        emitConfig() {
            this.$emit('config-changed', this.actualConfigData[this.currentSalesChannelId]);
        },

        kebabCase(value) {
            return kebabCase(value);
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, use `Shopware.Utils.isMeteorComponent` instead.
         */
        isMeteorComponent(element) {
            return isMeteorFieldComponent(element);
        },

        getMeteorElementBind(element, mapInheritance) {
            const bind = {};

            // Bind necessary props to sw-form-field-renderer
            bind.value = mapInheritance?.currentValue;
            bind.type = element.type;
            bind.config = { ...(element.config || {}) };

            // Inheritance bindings
            bind.inheritedValue = this.getInheritedValue(element);
            bind.isInheritanceField = mapInheritance?.isInheritField;
            bind.isInherited = mapInheritance?.isInherited;
            bind.disabled = mapInheritance?.isInherited || element.config?.disabled;

            // Handle datepicker date/datetime value format
            if (element.type === 'date') {
                bind.dateType = 'date';
            }

            if (element.type === 'datetime') {
                bind.dateType = 'datetime';
            }

            // Handle select properties
            if (
                [
                    'single-select',
                    'multi-select',
                ].includes(element.type)
            ) {
                bind.config.labelProperty = 'name';
                bind.config.valueProperty = 'id';

                if (bind.config.required) {
                    bind.config.hideClearableButton = true;
                }
            }

            // Handle multi select
            if (element.type === 'multi-select') {
                bind.enableMultiSelection = true;
            }

            return bind;
        },

        getMeteorElementEventsHandler(element, mapInheritance) {
            const eventHandler = {};

            eventHandler['update:value'] = mapInheritance?.updateCurrentValue;
            eventHandler['inheritance-remove'] = mapInheritance?.removeInheritance;
            eventHandler['inheritance-restore'] = mapInheritance?.restoreInheritance;

            return eventHandler;
        },
    },
};
