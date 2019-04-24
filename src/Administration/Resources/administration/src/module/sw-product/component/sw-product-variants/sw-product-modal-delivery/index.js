import { Component } from 'src/core/shopware';
import template from './sw-product-modal-delivery.html.twig';
import './sw-product-modal-delivery.scss';

Component.register('sw-product-modal-delivery', {
    template,

    inject: ['repositoryFactory', 'context'],

    props: {
        product: {
            type: Object,
            required: true
        },

        selectedGroups: {
            type: Array,
            required: true
        }
    },

    data() {
        return {
            activeTab: 'order',
            isLoading: false
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        }
    },

    methods: {
        saveDeliveryConfiguration() {
            this.isLoading = true;

            // Save the product after generating
            this.productRepository.save(this.product, this.context).then(() => {
                this.$emit('configuration-closed');
            });
        },

        cancelDeliveryConfiguration() {
            this.$emit('configuration-closed');
        }
    }
});
