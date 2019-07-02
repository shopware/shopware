import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-product-detail-properties.html.twig';
import './sw-product-detail-properties.scss';

const { mapState, mapGetters } = Component.getComponentHelper();

Component.register('sw-product-detail-properties', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            propertiesAvailable: true
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'context'
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading'
        ]),

        propertyRepository() {
            return this.repositoryFactory.create('property_group_option');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.checkIfPropertiesExists();
        },

        checkIfPropertiesExists() {
            this.propertyRepository.search(new Criteria(1, 1), this.context).then((res) => {
                this.propertiesAvailable = res.total > 0;
            });
        }
    }
});
