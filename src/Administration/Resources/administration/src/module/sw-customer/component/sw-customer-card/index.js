import { Component } from 'src/core/shopware';
import template from './sw-customer-card.html.twig';
import './sw-customer-card.scss';

Component.register('sw-customer-card', {
    template,

    props: {
        customer: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        title: {
            type: String,
            required: true,
            default: ''
        },
        editMode: {
            type: Boolean,
            required: false,
            default: false
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
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
            const customer = this.customer;

            if (!customer.firstName && !customer.lastName && !customer.company) {
                return '';
            }

            const salutation = customer.salutation ? customer.salutation : '';
            const title = customer.title ? customer.title : '';
            const firstName = customer.firstName ? customer.firstName : '';
            const lastName = customer.lastName ? customer.lastName : '';

            const company = customer.company ? customer.company : '';
            const mergedName = `${salutation} ${title} ${firstName} ${lastName}`;
            const dash = company.trim() ? ' - ' : '';

            return `${mergedName} ${dash} ${company}`.trim();
        }
    },

    methods: {
        getMailTo(mail) {
            return `mailto:${mail}`;
        }
    }
});
