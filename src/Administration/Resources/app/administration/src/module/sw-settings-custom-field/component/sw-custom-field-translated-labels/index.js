import template from './sw-custom-field-translated-labels.html.twig';
import './sw-custom-field-translated-labels.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-custom-field-translated-labels', {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
    ],

    model: {
        prop: 'config',
    },

    props: {
        // FIXME: add type to locales
        // eslint-disable-next-line vue/require-prop-types
        locales: {
            required: true,
        },
        config: {
            type: Object,
            required: true,
        },
        propertyNames: {
            type: Object,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        locale() {
            return this.$root.$i18n.locale;
        },
        fallbackLocale() {
            return this.$root.$i18n.fallbackLocale;
        },
        localeCount() {
            return Object.keys(this.locales).length;
        },
    },

    watch: {
        locales() {
            this.initializeConfiguration();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initializeConfiguration();
        },
        initializeConfiguration() {
            Object.keys(this.propertyNames).forEach((property) => {
                if (!this.config.hasOwnProperty(property)) {
                    this.$set(this.config, property, { [this.fallbackLocale]: null });
                }
            });
        },
        getLabel(label, locale) {
            const snippet = this.getInlineSnippet(label);
            const language = this.$tc(`locale.${locale}`);

            return `${snippet} (${language})`;
        },
        onInput(input, propertyName, locale) {
            if (input === '') {
                this.config[propertyName][locale] = null;
            }
        },
    },
});
