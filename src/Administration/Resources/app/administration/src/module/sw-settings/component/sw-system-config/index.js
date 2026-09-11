/**
 * @sw-package framework
 */
import { computed } from 'vue';
import ErrorResolverSystemConfig from 'src/core/data/error-resolver.system-config.data';
import { deepCloneWithEntity } from 'src/core/service/extension-api-data.service';
import utils from 'src/core/service/util.service';
import template from './sw-system-config.html.twig';
import './sw-system-config.scss';

const { Mixin } = Shopware;
const {
    object,
    types,
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

    /** @public */
    provide() {
        return {
            swSystemConfigCurrentSalesChannelId: computed(() => this.currentSalesChannelId),
        };
    },

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
            schema: [],
            /**
             * @deprecated tag:v6.8.0 - Will be removed, use schema instead.
             */
            config: [],
            actualConfigData: {},
            initialConfigData: {},
            salesChannelModel: null,
            hasCssFields: false,
            activeTab: null,
            /**
             * @deprecated tag:v6.8.0 - Will be removed together with config data prop.
             */
            isSyncingFromSchema: false,
            /**
             * @deprecated tag:v6.8.0 - Will be removed together with config data prop.
             */
            isSyncingFromConfig: false,
        };
    },

    computed: {
        isNotDefaultSalesChannel() {
            return this.currentSalesChannelId !== null;
        },

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

        showGlobalSection() {
            return this.showTabs || this.schema.at(0)?.cards.length > 1;
        },

        showTabs() {
            return this.schema?.length > 1;
        },

        tabItems() {
            return this.schema?.map((tab) => {
                return {
                    name: this.getTabName(tab),
                    label:
                        tab.title !== null
                            ? this.getInlineSnippet(tab.title)
                            : this.$t('sw-settings.system-config.tabGeneral'),
                };
            });
        },

        defaultTabItem() {
            return this.schema?.at(0) ? this.getTabName(this.schema.at(0)) : '';
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

        /**
         * @deprecated tag:v6.8.0 - Will be removed together with config data prop.
         */
        schema: {
            handler(newSchema) {
                if (this.isSyncingFromConfig) {
                    return;
                }

                this.isSyncingFromSchema = true;
                this.config = this.schemaToConfig(newSchema);
                this.$nextTick(() => {
                    this.isSyncingFromSchema = false;
                });
            },
            deep: true,
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed together with config data prop.
         */
        config: {
            handler(newConfig) {
                if (this.isSyncingFromSchema) {
                    return;
                }

                this.isSyncingFromConfig = true;
                this.schema = this.configToSchema(newConfig, this.schema);
                this.$nextTick(() => {
                    this.isSyncingFromConfig = false;
                });
            },
            deep: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        getFieldError(fieldName) {
            return mapSystemConfigErrors(ErrorResolverSystemConfig.ENTITY_NAME, this.currentSalesChannelId, fieldName);
        },

        async createdComponent() {
            this.isLoading = true;
            try {
                this.actualConfigData = {};
                this.initialConfigData = {};
                this.hasCssFields = false;

                await this.readConfig();
                await this.readAll();

                this.activeTab = this.getTabName(this.schema.at(0));
            } catch (error) {
                if (error?.response?.data?.errors) {
                    this.createErrorNotification(error.response.data.errors);
                } else {
                    this.createNotificationError({
                        message: this.$t('global.notification.notificationLoadingDataErrorMessage'),
                    });
                }
            } finally {
                this.isLoading = false;
            }
        },

        async readConfig() {
            const schema = await this.systemConfigApiService.getSchema(this.domain);

            // @deprecated tag:v6.8.0 - The assignConfigIds method call will be removed together with config data prop.
            this.assignConfigIds(schema);

            this.schema = schema;

            this.schema.every((tab) => {
                return tab?.cards.every((card) => {
                    return card?.elements.every((field) => {
                        if (field?.config?.css) {
                            this.hasCssFields = true;
                            return false;
                        }
                        return true;
                    });
                });
            });
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed together with config data prop.
         *
         * Tags cards and elements with an unofficial, internal id (`__configId`). It survives immutable
         * updates (e.g. `card.map((c) => ({ ...c, title }))`) because object spread copies it along with
         * the other properties, so it is used to reliably match cards back to their original tab in
         * `configToSchema`, regardless of mutation style or reordering.
         */
        assignConfigIds(schema) {
            (schema ?? []).forEach((tab) => {
                (tab.cards ?? []).forEach((card) => {
                    card.__configId = utils.createId();

                    (card.elements ?? []).forEach((element) => {
                        element.__configId = utils.createId();
                    });
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
                this.initialConfigData[this.currentSalesChannelId] = object.deepCopyObject(values);
            } finally {
                this.isLoading = false;
            }
        },

        saveAll() {
            this.isLoading = true;

            const changedConfigData = this.getChangedConfigData();
            if (!this.hasConfigChanges(changedConfigData)) {
                this.isLoading = false;
                return Promise.resolve();
            }

            const additionalParams = this.hasCacheRelevantChanges(changedConfigData) ? { silent: false } : {};

            return this.systemConfigApiService
                .batchSave(changedConfigData, additionalParams)
                .then(() => {
                    this.initialConfigData = object.deepCopyObject(this.actualConfigData);
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        getChangedConfigData() {
            const changedConfigData = {};

            Object.entries(this.actualConfigData).forEach((entry) => {
                const salesChannelId = entry[0];
                const config = entry[1];
                const initialConfig = this.initialConfigData[salesChannelId] ?? {};
                const changedConfig = {};

                Object.entries(config).forEach((configEntry) => {
                    const key = configEntry[0];
                    const value = configEntry[1];

                    if (types.isEqual(value, initialConfig[key])) {
                        return;
                    }

                    changedConfig[key] = value;
                });

                if (this.hasConfigChanges(changedConfig)) {
                    changedConfigData[salesChannelId] = changedConfig;
                }
            });

            return changedConfigData;
        },

        hasConfigChanges(configData) {
            return Object.keys(configData).length > 0;
        },

        hasCacheRelevantChanges(changedConfigData) {
            const cacheRelevantFieldNames = this.getCacheRelevantFieldNames();

            return Object.values(changedConfigData).some((config) => {
                return Object.keys(config).some((key) => {
                    return cacheRelevantFieldNames.has(key);
                });
            });
        },

        getCacheRelevantFieldNames() {
            const fieldNames = new Set();

            this.schema.forEach((tab) => {
                tab.cards?.forEach((card) => {
                    card.elements?.forEach((element) => {
                        if (element.config?.cacheRelevant === true) {
                            fieldNames.add(element.name);
                        }
                    });
                });
            });

            return fieldNames;
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

        hasMapInheritanceSupport(element) {
            const componentName = element.config ? element.config.componentName : undefined;

            if (componentName === 'sw-snippet-field') {
                return true;
            }

            return this.typesWithMapInheritanceSupport.includes(element.type);
        },

        getElementBind(element, mapInheritance) {
            const bind = object.deepCopyObject(element);

            if (!this.hasMapInheritanceSupport(element)) {
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
            if (this.hasMapInheritanceSupport(element)) {
                return {};
            }

            if (this.isMeteorComponent(element)) {
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
         * New methods for Meteor components
         */
        isMeteorComponent(element) {
            const componentName = element.config ? element.config.componentName : undefined;

            // Special case for sw-text-editor, because we still support the legacy one
            const componentsWithMeteorSupport = [
                'sw-text-editor',
            ];

            const typesWithMeteorSupport = [
                'bool',
                'switch',
                'text',
                'textarea',
                'url',
                'checkbox',
                'colorpicker',
                'password',
                'date',
                'datetime',
                'time',
                'single-select',
                'multi-select',
                'float',
                'int',
            ];

            return typesWithMeteorSupport.includes(element.type) || componentsWithMeteorSupport.includes(componentName);
        },

        getMeteorElementBind(element, mapInheritance) {
            const bind = {};

            // Bind necessary props to sw-form-field-renderer
            bind.value = mapInheritance?.currentValue;
            bind.type = element.type;
            bind.config = { ...(element.config || {}) };
            bind.error = this.getFieldError(element.name);

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

        getTabName(tab) {
            return `tab-${tab.name ?? this.schema.indexOf(tab)}`;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed together with config data prop.
         */
        schemaToConfig(schema) {
            return (schema ?? []).flatMap((tab) => tab?.cards ?? []);
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed together with config data prop.
         *
         * Keeps existing tabs instead of collapsing everything into a single tab. Cards are matched back to
         * their original tab via the internal `__configId` (see assignConfigIds), which is reliable across
         * reordering and immutable updates. Cards without a matching id (e.g. newly added ones, or ones that
         * lost the id because they were rebuilt from scratch instead of spread) end up in the general tab
         * (name === null) and are assigned a fresh id, so their tab is tracked correctly from then on.
         */
        configToSchema(config, previousSchema) {
            const cards = config ?? [];
            const previousTabs =
                previousSchema && previousSchema.length > 0
                    ? previousSchema
                    : [
                          {
                              name: null,
                              title: null,
                              cards: [],
                          },
                      ];

            const newSchema = previousTabs.map((tab) => ({ ...tab, cards: [] }));
            const generalTabIndex = previousTabs.findIndex((tab) => tab.name === null);
            const fallbackTabIndex = generalTabIndex !== -1 ? generalTabIndex : newSchema.length - 1;

            const idToTabIndex = new Map();
            previousTabs.forEach((tab, tabIndex) => {
                (tab.cards ?? []).forEach((card) => {
                    if (card.__configId !== undefined) {
                        idToTabIndex.set(card.__configId, tabIndex);
                    }
                });
            });

            cards.forEach((card) => {
                const tabIndex = idToTabIndex.has(card.__configId) ? idToTabIndex.get(card.__configId) : fallbackTabIndex;

                if (card.__configId === undefined) {
                    card.__configId = utils.createId();
                }

                (card.elements ?? []).forEach((element) => {
                    if (element.__configId === undefined) {
                        element.__configId = utils.createId();
                    }
                });

                newSchema[tabIndex].cards.push(card);
            });

            return newSchema;
        },
    },
};
