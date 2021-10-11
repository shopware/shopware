import template from './sw-flow-set-entity-custom-field-modal.html.twig';
import './sw-flow-set-entity-custom-field-modal.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();
const { ShopwareError } = Shopware.Classes;

Component.register('sw-flow-set-entity-custom-field-modal', {
    flag: 'FEATURE_NEXT_17973',
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
            fieldOptionSelected: 'overwrite',
        };
    },

    computed: {
        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(
                Criteria.equalsAny('relations.entityName', Object.keys(this.triggerEvent.data)),
            );

            return criteria;
        },

        customFieldCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(
                Criteria.equals('customFieldSetId', this.customFieldSetId),
            );

            return criteria;
        },

        showFieldValue() {
            return this.customFieldId && this.fieldOptionSelected !== 'clear';
        },

        defaultFieldOptions() {
            return [
                {
                    id: 'overwrite',
                    name: `${this.$tc('sw-flow.modals.setEntityCustomField.options.overwrite')}`,
                },
                {
                    id: 'skipOnExisted',
                    name: `${this.$tc('sw-flow.modals.setEntityCustomField.options.notOverwrite')}`,
                },
                {
                    id: 'clear',
                    name: `${this.$tc('sw-flow.modals.setEntityCustomField.options.clear')}`,
                },
            ];
        },

        multipleFieldOptions() {
            return [
                ...this.defaultFieldOptions,
                {
                    id: 'add',
                    name: `${this.$tc('sw-flow.modals.setEntityCustomField.options.add')}`,
                },
                {
                    id: 'remove',
                    name: `${this.$tc('sw-flow.modals.setEntityCustomField.options.remove')}`,
                },
            ];
        },

        ...mapState('swFlowState', ['triggerEvent', 'customFieldSets', 'customFields']),
    },

    watch: {
        renderedFieldConfig(value) {
            if (value.customFieldType === 'colorpicker' && !this.renderedFieldConfig.zIndex) {
                this.renderedFieldConfig = {
                    ...this.renderedFieldConfig,
                    zIndex: 1001,
                };
            }
            this.fieldOptions = this.getFieldOptions(this.renderedFieldConfig);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.sequence.config) {
                return;
            }

            this.customFieldSetId = this.sequence.config.customFieldSetId;
            this.customFieldSetLabel = this.sequence.config.customFieldSetLabel;
            this.customFieldId = this.sequence.config.customFieldId;
            this.customFieldLabel = this.sequence.config.customFieldLabel;
            this.customFieldValue = this.sequence.config.customFieldValue;

            this.getCustomFieldRendered();
        },

        getCustomFieldRendered() {
            this.customFieldRepository.get(this.customFieldId).then((customField) => {
                this.renderedFieldConfig = this.validateOptionSelectFieldLabel(customField.config);
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                });
            }).finally(() => {
                this.fieldOptionSelected = this.sequence.config.option;
            });
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
            this.customFieldSetError = this.fieldError(this.customFieldSetId);
            this.customFieldError = this.customFieldSetId ? this.fieldError(this.customFieldId) : null;
            if (this.customFieldSetError || this.customFieldError) {
                return;
            }

            const sequence = {
                ...this.sequence,
                config: {
                    customFieldSetId: this.customFieldSetId,
                    customFieldId: this.customFieldId,
                    customFieldValue: this.customFieldValue,
                    option: this.fieldOptionSelected,
                    optionLabel: this.fieldOptions.find((option) => {
                        return option.id === this.fieldOptionSelected;
                    })?.name,
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
    },
});
