import { Component, State } from 'src/core/shopware';
import template from './sw-customer-address-form.html.twig';
import './sw-customer-address-form.scss';

Component.register('sw-customer-address-form', {
    template,

    props: {
        customer: {
            type: Object,
            required: true
        },

        address: {
            type: Object,
            required: true,
            default: {}
        },

        countries: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        }
    },

    data() {
        return {
            salutations: null
        };
    },

    computed: {
        salutationStore() {
            return State.getStore('salutation');
        }
    },

    created() {
        this.createdComponents();
    },

    methods: {
        createdComponents() {
            this.salutationStore.getList({ page: 1, limit: 500 }).then(({ items }) => {
                this.salutations = items;
            });
        }
    }
});
