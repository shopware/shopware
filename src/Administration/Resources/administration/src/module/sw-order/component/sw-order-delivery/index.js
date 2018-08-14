import { Component } from 'src/core/shopware';
import template from './sw-order-delivery.html.twig';
import './sw-order-delivery.less';

Component.register('sw-order-delivery', {
    template,

    props: {
        delivery: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        title: {
            type: String,
            required: false
        },
        order: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
    },

    data() {
        return {
            isLoading: false
        };
    },

    computed: {
        positionsStore() {
            return this.delivery.getAssociationStore('positions');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.positionsStore.getList({
                page: 1,
                limit: 50
            }).then(() => {
                this.isLoading = false;
            });
        }
    }
});
