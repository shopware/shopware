import template from './sw-cms-page-select.html.twig';
import './sw-cms-page-select.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-cms-page-select', {
    template,

    inject: ['cmsService'],

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
    ],

    props: {
        pageType: {
            type: String,
            required: true,
        },

        value: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            currentValue: this.value,
        };
    },

    computed: {
        bind() {
            return {
                ...this.$attrs,
                ...this.translations,
            };
        },

        translations() {
            return this.getTranslations();
        },

        pageTypeCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(
                Criteria.equals('type', this.pageType),
            );

            return criteria;
        },
    },

    watch: {
        currentValue(value) {
            if (value !== this.value) {
                this.$emit('input', value);
            }
        },

        value() {
            this.currentValue = this.value;
        },
    },

    methods: {
        getTranslations() {
            const translatableFields = ['label', 'placeholder', 'helpText'];

            const translations = {};
            translatableFields.forEach((field) => {
                if (this.$attrs[field] && this.$attrs[field] !== '') {
                    translations[field] = this.getInlineSnippet(this.$attrs[field]);
                }
            });

            return translations;
        },
    },
});
