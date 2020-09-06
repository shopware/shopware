import template from './sw-product-detail-properties.html.twig';
import './sw-product-detail-properties.scss';

const { Component, Data } = Shopware;
const { RepositoryIterator } = Data;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-detail-properties', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            propertiesAvailable: true,
            isInherited: false
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct'
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading',
            'isChild'
        ]),

        propertyRepository() {
            return this.repositoryFactory.create('property_group_option');
        }
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.checkIfPropertiesExists();
        },

        mountedComponent() {
            this.isInherited = this.isChild && !this.product.options.total;
        },

        checkIfPropertiesExists() {
            const iterator = new RepositoryIterator(this.propertyRepository);
            iterator.getTotal().then(total => {
                this.propertiesAvailable = total > 0;
            });
        },

        restoreInheritance() {
            this.isInherited = true;
        },

        removeInheritance() {
            this.isInherited = false;
        }
    }
});
