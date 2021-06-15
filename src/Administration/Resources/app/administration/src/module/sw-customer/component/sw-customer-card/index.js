import template from './sw-customer-card.html.twig';
import './sw-customer-card.scss';
import errorConfig from '../../error-config.json';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-customer-card', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('salutation'),
    ],

    props: {
        customer: {
            type: Object,
            required: true,
        },
        title: {
            type: String,
            required: true,
        },
        editMode: {
            type: Boolean,
            required: false,
            default: false,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        hasActionSlot() {
            return !!this.$slots.actions;
        },

        hasAdditionalDataSlot() {
            return !!this.$slots['data-additional'];
        },

        hasSummarySlot() {
            return !!this.$slots.summary;
        },

        moduleColor() {
            if (!this.$route.meta.$module) {
                return '';
            }
            return this.$route.meta.$module.color;
        },

        fullName() {
            const name = {
                name: this.salutation(this.customer),
                company: this.customer.company,
            };

            return Object.values(name).filter(item => item !== null).join(' - ').trim();
        },

        ...mapPropertyErrors('customer', errorConfig['sw.customer.detail.base'].customer),
    },

    methods: {
        getMailTo(mail) {
            return `mailto:${mail}`;
        },
    },
});
