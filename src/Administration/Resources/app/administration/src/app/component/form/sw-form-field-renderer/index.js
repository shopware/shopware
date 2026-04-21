import template from './sw-form-field-renderer.html.twig';

const { Mixin } = Shopware;
const { types } = Shopware.Utils;

const FILTERED_ATTR_KEYS = ['onUpdate:value'];

const SELECT_COMPONENTS = [
    'sw-single-select',
    'sw-multi-select',
    'mt-select',
];

const TYPE_COMPONENT_MAP = {
    bool: 'mt-switch',
    switch: 'mt-switch',
    textarea: 'mt-textarea',
    checkbox: 'mt-checkbox',
    colorpicker: 'mt-colorpicker',
    compactColorpicker: 'sw-compact-colorpicker',
    date: 'mt-datepicker',
    datetime: 'mt-datepicker',
    time: 'mt-datepicker',
    email: 'mt-email-field',
    float: 'mt-number-field',
    int: 'mt-number-field',
    number: 'mt-number-field',
    'multi-entity-id-select': 'sw-entity-multi-id-select',
    'multi-select': 'mt-select',
    password: 'mt-password-field',
    price: 'sw-price-field',
    radio: 'sw-radio-field',
    'single-entity-id-select': 'sw-entity-single-select',
    'single-select': 'mt-select',
    string: 'mt-text-field',
    text: 'mt-text-field',
    tagged: 'sw-tagged-field',
    url: 'mt-url-field',
};

const TYPE_FIELD_PROPS = {
    int: { type: 'number', numberType: 'int' },
    float: { type: 'number', numberType: 'float' },
    string: { type: 'text' },
    text: { type: 'text' },
    bool: { type: 'switch', bordered: true },
    datetime: { type: 'date', dateType: 'datetime' },
    date: { type: 'date', dateType: 'date' },
    time: { type: 'date', dateType: 'time' },
};

const COMPAT_COMPUTED_WRAPPERS = {
    bind() {
        return {
            ...this.filteredAttrs,
            ...(this.config ?? {}),
            ...this.swFieldType,
            ...this.translations,
            ...this.optionTranslations,
            ...this.specialComponentBindings,
        };
    },

    filteredAttrs() {
        return this.baseRenderPlan.filteredAttrs;
    },

    componentName() {
        return this.baseRenderPlan.componentName;
    },

    swFieldType() {
        return this.baseRenderPlan.normalizedTypeProps;
    },

    translations() {
        return this.getTranslations(this.componentName);
    },

    optionTranslations() {
        return this.resolveTranslatedOptionProps();
    },

    shouldFetchSystemCurrency() {
        return this.resolveNeedsSystemCurrency();
    },

    specialComponentBindings() {
        return this.resolveComponentSpecificProps();
    },

    componentPropName() {
        return this.resolveComponentPropName();
    },
};
/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description
 * Dynamically renders components with a given configuration. The rendered component can be forced by defining
 * the config.componentName property. If not set the form-field-renderer will guess a suitable
 * component for the type. Everything inside the config prop will be passed to the rendered child prop as properties.
 * Also all additional props will be passed to the child.
 *
 * Internal overview:
 * 1. `baseRenderPlan` resolves the default child component, filtered attrs, and normalized type props.
 * 2. Backward-compatible wrapper computeds (`componentName`, `swFieldType`, `translations`, `optionTranslations`,
 *    `specialComponentBindings`, `componentPropName`, `shouldFetchSystemCurrency`) stay available for overrides and
 *    still drive the final `bind` object.
 * 3. The template renders the resolved child dynamically and forwards all slots and legacy/Meteor update events.
 * 4. Watchers keep `currentValue` in sync with the parent `value` prop and emit `update:value` when the child changes.
 * @example-type code-only
 * @component-example
 * {# Datepicker #}
 * <sw-form-field-renderer
 *     v-model="yourValue"
 *     type="datetime">
 * </sw-form-field-renderer>
 *
 * {# Text field #}
 * <sw-form-field-renderer
 *     v-model="yourValue"
 *     type="string">
 * </sw-form-field-renderer>
 *
 * {# sw-number-field #}
 * <sw-form-field-renderer
 *     v-model="yourValue"
 *     :config="{
 *         componentName: 'sw-field',
 *         type: 'number',
 *         numberType: 'float'
 *     }">
 * </sw-form-field-renderer>
 *
 * {# sw-select - multi #}
 * <sw-form-field-renderer
 *     v-model="yourValue"
 *     :config="{
 *         componentName: 'sw-multi-select',
 *         label: {
 *             'en-GB': 'Multi Select'
 *         },
 *         multi: true,
 *         options: [
 *             { value: 'option1', label: { 'en-GB': 'One' } },
 *             { value: 'option2', label: 'Two' },
 *             { value: 'option3', label: { 'en-GB': 'Three', 'de-DE': 'Drei' } }
 *         ]
 *     }">
 * </sw-form-field-renderer>
 *
 * {# sw-select - single #}
 * <sw-form-field-renderer
 *     v-model="yourValue"
 *     :componentName: 'sw-single-select',
 *     :config="{
 *         label: 'Single Select',
 *         options: [
 *             { value: 'option1', label: { 'en-GB': 'One' } },
 *             { value: 'option2', label: 'Two' },
 *             { value: 'option3', label: { 'en-GB': 'Three', 'de-DE': 'Drei' } }
 *         ]
 *     }">
 * </sw-form-field-renderer>
 */
export default {
    template,

    inheritAttrs: false,

    inject: [
        'repositoryFactory',
        'feature',
    ],

    emits: ['update:value'],

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
    ],

    props: {
        type: {
            type: String,
            required: false,
            default: null,
        },
        config: {
            type: Object,
            required: false,
            default: null,
        },
        value: {
            required: true,
        },
        error: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            currency: { id: Shopware.Context.app.systemCurrencyId, factor: 1 },
            // @deprecated tag:v6.8.0 - legacy data field kept for backward compatibility with administration overrides. Currently unused in core and will be removed in v6.8.0.
            currentComponentName: '',
            // @deprecated tag:v6.8.0 - legacy data field kept for backward compatibility with administration overrides. Currently unused in core and will be removed in v6.8.0.
            swFieldConfig: {},
            currentValue: this.getInitialValue(this.type, this.value, Shopware.Context.app.systemCurrencyId),
        };
    },

    computed: {
        // Internal default resolution for how the child field should be rendered.
        baseRenderPlan() {
            return this.buildBaseRenderPlan();
        },

        hasConfig() {
            return !!this.config;
        },

        // Backward-compatible wrappers kept for existing overrides and templates.
        ...COMPAT_COMPUTED_WRAPPERS,
    },

    watch: {
        currentValue: {
            handler(value) {
                if (!this.isSameValue(value, this.value)) {
                    this.$emit('update:value', value);
                }
            },
            deep: true,
        },
        shouldFetchSystemCurrency(value) {
            if (value) {
                this.fetchSystemCurrency();
            }
        },
        value() {
            this.currentValue = this.value;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.shouldFetchSystemCurrency) {
                this.fetchSystemCurrency();
            }
        },

        emitUpdate(data) {
            this.$emit('update:value', data);
        },

        filterAttrs(attrs) {
            const filteredAttrs = {};

            Object.keys(attrs).forEach((key) => {
                if (!FILTERED_ATTR_KEYS.includes(key)) {
                    filteredAttrs[key] = attrs[key];
                }
            });

            return filteredAttrs;
        },

        translateFields(config, translatableFields, getInlineSnippet = (value) => this.getInlineSnippet(value)) {
            if (!config || !translatableFields) {
                return {};
            }

            const translations = {};

            translatableFields.forEach((field) => {
                if (config[field] && config[field] !== '') {
                    translations[field] = getInlineSnippet(config[field]);
                }
            });

            return translations;
        },

        translateOptions(options, labelProperty, getInlineSnippet = (value) => this.getInlineSnippet(value)) {
            return options.map((option) => {
                const translatedOption = {
                    ...option,
                    ...this.translateFields(option, [labelProperty], getInlineSnippet),
                };

                if (!translatedOption.label) {
                    translatedOption.label = option.value;
                }

                return translatedOption;
            });
        },

        getInitialValue(type, value, systemCurrencyId) {
            if (type === 'price' && !value && !Array.isArray(value)) {
                return [
                    {
                        currencyId: systemCurrencyId,
                        gross: null,
                        net: null,
                        linked: true,
                    },
                ];
            }

            return value;
        },

        isSameValue(value, sourceValue) {
            if (
                Array.isArray(value) &&
                Array.isArray(sourceValue) &&
                value.length === sourceValue.length &&
                value.every((val, index) => val === sourceValue[index])
            ) {
                return true;
            }

            return value === sourceValue;
        },

        buildBaseRenderPlan() {
            return {
                componentName: this.resolveComponentName(),
                filteredAttrs: this.filterAttrs(this.$attrs),
                normalizedTypeProps: this.resolveNormalizedTypeProps(),
            };
        },

        resolveComponentName() {
            if (!this.config) {
                return this.getComponentFromType();
            }

            if (this.config.componentName === 'sw-field') {
                return this.getComponentFromType(this.config.type);
            }

            return this.config.componentName || this.getComponentFromType();
        },

        resolveNormalizedTypeProps() {
            if (this.type === 'price') {
                return {
                    type: 'price',
                    allowModal: true,
                    hideListPrices: true,
                    currency: this.currency,
                };
            }

            if (this.config?.hasOwnProperty('type')) {
                return {};
            }

            return TYPE_FIELD_PROPS[this.type] ?? { type: this.type };
        },

        resolveTranslatedOptionProps() {
            if (!SELECT_COMPONENTS.includes(this.componentName) || !this.config?.hasOwnProperty('options')) {
                return {};
            }

            return {
                options: this.translateOptions(
                    this.config.options,
                    this.config.labelProperty ?? 'label',
                    (value) => this.getInlineSnippet(value),
                ),
            };
        },

        resolveComponentSpecificProps() {
            if (this.componentName !== 'sw-entity-multi-id-select') {
                return {};
            }

            return {
                repository: this.createRepository(this.config.entity),
            };
        },

        resolveComponentPropName() {
            if (this.componentName.startsWith('mt-')) {
                return 'modelValue';
            }

            return 'value';
        },

        resolveNeedsSystemCurrency() {
            return this.type === 'price' || this.componentName === 'sw-price-field';
        },

        getTranslations(
            _componentName,
            config = this.config,
            translatableFields = [
                'label',
                'placeholder',
                'helpText',
            ],
        ) {
            return this.translateFields(config, translatableFields, (value) => this.getInlineSnippet(value));
        },

        getComponentFromType(customType = undefined) {
            const type = customType ?? this.type;

            return TYPE_COMPONENT_MAP[type] ?? 'mt-text-field';
        },

        createRepository(entity) {
            if (types.isUndefined(entity)) {
                throw new Error('sw-form-field-renderer - sw-entity-multi-id-select component needs entity property');
            }

            return this.repositoryFactory.create(entity);
        },

        fetchSystemCurrency() {
            const systemCurrencyId = Shopware.Context.app.systemCurrencyId;

            this.createRepository('currency')
                .get(systemCurrencyId)
                .then((response) => {
                    this.currency = response;
                });
        },

        getScopedSlots() {
            return this.$slots;
        },
    },
};
