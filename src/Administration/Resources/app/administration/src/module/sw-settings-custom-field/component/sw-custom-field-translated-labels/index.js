/**
 * @sw-package framework
 */
import template from './sw-custom-field-translated-labels.html.twig';
import './sw-custom-field-translated-labels.scss';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'feature',
    ],

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
    ],

    props: {
        locales: {
            type: Array,
            required: true,
            default: [],
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

    data() {
        return {
            activeTab: null,
        };
    },

    computed: {
        fallbackLocale() {
            return this.$root.$i18n.fallbackLocale.value;
        },

        localeCount() {
            return this.locales.length;
        },

        activeLocale() {
            return this.activeTab ?? this.fallbackLocale;
        },

        translatedLabelTabs() {
            return this.locales.map((locale) => {
                return {
                    label: this.$t(`locale.${locale}`),
                    name: locale,
                };
            });
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
                    this.config[property] = { [this.fallbackLocale]: null };
                }
            });
        },

        getLabel(label, locale) {
            const snippet = this.getInlineSnippet(label);
            const language = this.$t(`locale.${locale}`);

            return `${snippet} (${language})`;
        },

        onInput(input, propertyName, locale) {
            if (input === '') {
                this.config[propertyName][locale] = null;
            }
        },
    },
};
