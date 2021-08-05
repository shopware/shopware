import template from './sw-settings-captcha-select.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-settings-captcha-select', {
    template,

    inject: ['feature', 'captchaService'],

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
    ],

    props: {
        value: {
            type: Array,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            availableCaptchas: [],
        };
    },

    computed: {
        captchas() {
            return this.availableCaptchas;
        },

        attributes() {
            return {
                ...this.$attrs,
                ...this.translations,
            };
        },

        translations() {
            return this.getTranslations();
        },

        currentValue: {
            get() {
                return this.value;
            },

            set(val) {
                if (val !== this.value) {
                    this.$emit('input', val);
                }
            },
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.captchaService.list(this.setCaptchaOptions);
        },

        setCaptchaOptions(list) {
            this.availableCaptchas = list.map(technicalName => this.renderCaptchaOption(technicalName));
        },

        renderCaptchaOption(technicalName) {
            return {
                label: this.$tc(`sw-settings-basic-information.captcha.label.${technicalName}`),
                value: technicalName,
            };
        },

        getTranslations() {
            return ['label', 'placeholder', 'helpText']
                .filter(name => !!this.$attrs[name])
                .reduce((translations, name) => ({
                    [name]: this.getInlineSnippet(this.$attrs[name]),
                    ...translations,
                }), {});
        },
    },
});
