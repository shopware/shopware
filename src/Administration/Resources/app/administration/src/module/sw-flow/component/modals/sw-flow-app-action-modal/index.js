import template from './sw-flow-app-action-modal.html.twig';

const { Criteria } = Shopware.Data;
const { Component, Mixin, Classes: { ShopwareError } } = Shopware;
const { mapState } = Component.getComponentHelper();

Component.register('sw-flow-app-action-modal', {
    template,

    inject: [
        'acl',
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('placeholder'),
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
            isLoading: false,
            config: {},
            fields: [],
            errors: {},
        };
    },

    computed: {
        actionLabel() {
            return this.sequence?.propsAppFlowAction?.translated?.label || this.sequence?.propsAppFlowAction?.label;
        },
        ...mapState('swFlowState', ['appFlowActionEntities']),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getFields();
            if (this.sequence?.config) {
                if (!this.sequence?.config) {
                    return;
                }

                Object.entries({ ...this.sequence.config }).forEach(([key, configValue]) => {
                    this.config[key] = (typeof configValue === 'object' && configValue.value !== undefined)
                        ? configValue.value
                        : configValue;
                });
            }
        },

        async onChange(event, field) {
            const val = event.value;
            this.handleValid(field, val);

            if (field?.entity === undefined) {
                return;
            }

            let data = null;
            let value = [event.field];
            if (event.field === null) {
                value = await this.fetchEntities(field.entity, event.value);
            }

            if (!value) {
                return;
            }

            data = {
                entity: field.entity,
                value,
            };

            Shopware.State.commit('swFlowState/setAppFlowActionEntities', data);
        },

        fetchEntities(entity, ids = []) {
            this.appFlowActionEntities[entity]?.getIds().forEach(id => {
                ids = [...ids, id];
            });

            if (ids.length === 0) {
                return null;
            }

            const criteria = new Criteria(1, ids.length);
            criteria.setIds(ids);

            return this.repositoryFactory.create(entity).search(criteria, Shopware.Context.api);
        },

        isValid() {
            this.errors = {};
            this.fields.forEach(field => {
                const val = this.config[field.name] ?? null;
                this.handleValid(field, val);
            });

            return Object.keys(this.errors).length === 0;
        },

        handleValid(field, val) {
            let value = val;
            if ((typeof value === 'object' && value?.length === 0)
                || (typeof value === 'object' && value?.value?.length === 0)
            ) {
                value = null;
            }

            if (field.required && !value && typeof value !== 'boolean') {
                this.$delete(this.config, [field.name]);
                this.$set(this.errors, field.name, new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                }));
                return;
            }

            this.$delete(this.errors, [field.name]);
        },

        onSave() {
            if (!this.isValid()) {
                return;
            }

            const config = this.buildConfig();
            const data = {
                ...this.sequence,
                config,
            };
            this.$emit('process-finish', data);
            this.onClose();
        },

        buildConfig() {
            const data = {};
            this.fields.forEach(field => {
                if (field.entity !== undefined && this.config[field.name]?.length !== 0) {
                    data[field.name] = {
                        entity: field.entity,
                        value: this.config[field.name],
                    };

                    return;
                }

                if (this.config[field.name]?.length !== 0 && this.config[field.name] !== null) {
                    data[field.name] = this.config[field.name];
                }
            });

            return data;
        },

        onClose() {
            this.$emit('modal-close');
        },

        getFields() {
            this.sequence.propsAppFlowAction?.config.forEach((config) => {
                this.config[config.name] = this.convertDefaultValue(config.type, config.defaultValue);
                this.fields.push(config);
                this.$delete(this.errors, config.name);
            });
        },

        convertDefaultValue(type, value) {
            if (value === undefined) {
                return null;
            }

            if (['int', 'float'].includes(type)) {
                return parseInt(value, 10);
            }

            if (['bool', 'checkbox'].includes(type)) {
                return !!value;
            }

            if (['date', 'datetime', 'time'].includes(type)) {
                return null;
            }

            return value;
        },

        getConfig(field) {
            const config = {
                label: field.label,
                placeholder: field.placeHolder,
                disabled: field.disabled,
                required: field.required,
                helpText: this.helpText(field),
            };

            if (field.type === 'colorpicker') {
                config.componentName = 'sw-colorpicker';
                config.zIndex = 1000;
                config.colorOutput = 'hex';

                return config;
            }

            if (field.type === 'text-editor') {
                config.componentName = 'sw-text-editor';

                return config;
            }

            if (['single-select', 'multi-select'].includes(field.type)) {
                config.componentName = `sw-${field.type}`;
                config.options = field.options;

                return config;
            }

            if (field.componentName) {
                config.componentName = field.componentName;
            }

            if (field.componentName && field.entity) {
                config.entity = field.entity;
                config.labelProperty = this.convertLabelProperty(field.entity);
                config.labelCallback = (item) => this.convertLabelCallback(config.entity, item);
            }

            if (['sw-entity-multi-select', 'sw-entity-multi-id-select'].includes(field.componentName)) {
                if (this.config[field.name] === null) {
                    this.config[field.name] = [];
                }

                config.componentName = 'sw-entity-multi-id-select';
            }

            return config;
        },

        convertLabelProperty(entity) {
            if (entity === 'order') {
                return 'orderNumber';
            }

            return 'name';
        },

        convertLabelCallback(entity, item) {
            if (entity === 'customer') {
                return `${item?.firstName || ''} ${item?.lastName || ''}`;
            }

            if (['category', 'promotion'].includes(entity)) {
                return `${item?.translated?.name || item?.name || ''}`;
            }

            return '';
        },

        helpText(field) {
            if (field.helpText === undefined) {
                return null;
            }

            const objHelpText = JSON.parse(JSON.stringify(field.helpText));
            const lang = Shopware.State.get('session').currentLocale;

            return objHelpText[lang] ?? objHelpText['en-GB'] ?? null;
        },
    },
});
