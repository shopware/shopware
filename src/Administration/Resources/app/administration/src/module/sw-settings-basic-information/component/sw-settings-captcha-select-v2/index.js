import template from './sw-settings-captcha-select-v2.html.twig';
import './sw-settings-captcha-select-v2.scss';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature', 'captchaService'],

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
    ],

    props: {
        value: {
            type: Object,
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
        attributes() {
            return {
                ...this.$attrs,
                ...this.getTranslations(),
            };
        },

        currentValue: {
            get() {
                return this.value;
            },

            set(val) {
                this.$emit('input', val);
            },
        },

        activeCaptchaSelect: {
            get() {
                const captchaSelected = [];
                Object.keys(this.currentValue).forEach(key => {
                    if (this.currentValue[key].isActive) {
                        captchaSelected.push(key);
                    }
                });

                return captchaSelected;
            },

            set(val) {
                if (val !== this.activeCaptchaSelect) {
                    Object.keys(this.currentValue).forEach(key => {
                        this.currentValue[key].isActive = val.includes(key);
                    });
                }
            },
        },
    },

    watch: {
        currentValue: {
            deep: true,
            handler(val) {
                this.$emit('input', val);
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
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
                    ...translations,
                    [name]: this.getInlineSnippet(this.$attrs[name]),
                }), {});
        },
    },
};
