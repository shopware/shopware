import template from './sw-flow-set-entity-custom-field-modal.html.twig';
import './sw-flow-set-entity-custom-field-modal.scss';

const { Component, Mixin, Service } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();
const { ShopwareError } = Shopware.Classes;

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
        Mixin.getByName('notification'),
    ],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
        action: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            customFieldSetId: null,
            customFieldId: null,
            customFieldValue: null,
            customFieldSetError: null,
            customFieldError: null,
            config: {},
            renderedFieldConfig: {},
            fieldOptions: [],
            fieldOptionSelected: 'upsert',
            customField: {
                config: {
                    label: '',
                },
            },
            entity: null,
            entityError: null,
            entityOptions: [],
        };
    },

    computed: {
        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        customFieldCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(
                Criteria.equals('customFieldSetId', this.customFieldSetId),
            );

            return criteria;
        },

        customFieldSetCriteria() {
            if (!this.entity) {
                return null;
            }

            const criteria = new Criteria(1, 25);
            criteria.addFilter(
                Criteria.equals('relations.entityName', this.convertToEntityTechnicalName(this.entity)),
            );

            return criteria;
        },

        showFieldValue() {
            return this.customFieldId && this.fieldOptionSelected !== 'clear';
        },

        defaultFieldOptions() {
            return [
                {
                    value: 'upsert',
                    label: `${this.$tc('sw-flow.modals.setEntityCustomField.options.overwrite')}`,
                },
                {
                    value: 'create',
                    label: `${this.$tc('sw-flow.modals.setEntityCustomField.options.notOverwrite')}`,
                },
                {
                    value: 'clear',
                    label: `${this.$tc('sw-flow.modals.setEntityCustomField.options.clear')}`,
                },
            ];
        },

        multipleFieldOptions() {
            return [
                ...this.defaultFieldOptions,
                {
                    value: 'add',
                    label: `${this.$tc('global.default.add')}`,
                },
                {
                    value: 'remove',
                    label: `${this.$tc('global.default.remove')}`,
                },
            ];
        },

        labelProperty() {
            return `config.label.${Shopware.State.get('session').currentLocale}`;
        },

        ...mapState('swFlowState', ['triggerEvent', 'customFieldSets', 'customFields', 'triggerActions']),
    },

    watch: {
        entity(value) {
            if (value && this.entityError) {
                this.entityError = null;
            }
        },

        renderedFieldConfig(value) {
            if (value.customFieldType === 'colorpicker' && !this.renderedFieldConfig.zIndex) {
                this.renderedFieldConfig = {
                    ...this.renderedFieldConfig,
                    zIndex: 1001,
                };
            }
            this.fieldOptions = this.getFieldOptions(this.renderedFieldConfig);
        },

        customFieldSetId(value) {
            if (value && this.customFieldSetError) {
                this.customFieldSetError = null;
            }
        },

        customFieldId(value) {
            if (value && this.customFieldError) {
                this.customFieldError = null;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getEntityOptions();
            if (!this.sequence.config) {
                return;
            }

            this.entity = this.sequence.config.entity;
            this.customFieldSetId = this.sequence.config.customFieldSetId;
            this.customFieldSetLabel = this.sequence.config.customFieldSetLabel;
            this.customFieldId = this.sequence.config.customFieldId;
            this.customFieldLabel = this.sequence.config.customFieldLabel;
            this.customFieldValue = this.sequence.config.customFieldValue;

            this.getCustomFieldRendered();
        },

        getCustomFieldRendered() {
            this.customFieldRepository.get(this.customFieldId).then((customField) => {
                this.customField = customField;
                this.renderedFieldConfig = this.validateOptionSelectFieldLabel(customField.config);
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                });
            }).finally(() => {
                this.fieldOptionSelected = this.sequence.config.option;
            });
        },

        onEntityChange() {
            this.customFieldSetId = null;
        },

        onCustomFieldSetChange(id, customFieldSet) {
            if (!customFieldSet) {
                return;
            }
            Shopware.State.commit('swFlowState/setCustomFieldSets', [...this.customFieldSets, customFieldSet]);
            this.customFieldId = null;
            this.customFieldValue = null;
            this.renderedFieldConfig = {};
        },

        onCustomFieldChange(id, customField) {
            if (!customField) {
                return;
            }
            this.customField = customField;

            Shopware.State.commit('swFlowState/setCustomFields', [...this.customFields, customField]);
            this.customFieldValue = null;
            this.renderedFieldConfig = this.validateOptionSelectFieldLabel(customField.config);
            if (this.renderedFieldConfig.componentName === 'sw-entity-multi-id-select') {
                this.customFieldValue = [];
            }
        },

        validateOptionSelectFieldLabel(config) {
            if (!config.options) {
                return config;
            }

            config.options.forEach((option) => {
                option.label = this.getInlineSnippet(option.label) || option.value;
            });

            return config;
        },

        onClose() {
            this.customFieldSetError = null;
            this.customFieldError = null;
            this.$emit('modal-close');
        },

        onAddAction() {
            this.entityError = this.fieldError(this.entity);
            this.customFieldSetError = this.entity ? this.fieldError(this.customFieldSetId) : null;
            this.customFieldError = this.customFieldSetId ? this.fieldError(this.customFieldId) : null;
            if (this.customFieldSetError || this.customFieldError || this.entityError) {
                return;
            }

            const sequence = {
                ...this.sequence,
                config: {
                    entity: this.entity,
                    customFieldSetId: this.customFieldSetId,
                    customFieldId: this.customFieldId,
                    customFieldValue: this.customFieldValue,
                    option: this.fieldOptionSelected,
                    optionLabel: this.fieldOptions.find((option) => {
                        return option.value === this.fieldOptionSelected;
                    })?.label,
                },
            };

            this.$emit('process-finish', sequence);
        },

        fieldError(field) {
            if (!field || !field.length) {
                return new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });
            }

            return null;
        },

        getFieldOptions(fieldConfig) {
            switch (fieldConfig.componentName) {
                case 'sw-entity-multi-id-select':
                case 'sw-multi-select':
                    return this.multipleFieldOptions;
                default:
                    return this.defaultFieldOptions;
            }
        },

        getEntityOptions() {
            if (!this.triggerEvent) {
                this.entityOptions = [];
                return;
            }

            const allowedAware = this.triggerEvent.aware ?? [];
            // eslint-disable-next-line max-len
            const options = Service('flowBuilderService').getAvailableEntities(this.action, this.triggerActions, allowedAware, ['customFields']);

            if (options.length) {
                this.entity = options[0].value;
            }

            this.entityOptions = options;
        },

        convertToEntityTechnicalName(camelCaseText) {
            return camelCaseText.replace(/[A-Z]/g, letter => `_${letter.toLowerCase()}`);
        },
    },
};
