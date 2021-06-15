import template from './sw-product-detail-properties.html.twig';
import './sw-product-detail-properties.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-detail-properties', {
    template,

    inject: ['repositoryFactory', 'acl'],

    data() {
        return {
            propertiesAvailable: true,
            isInherited: false,
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading',
            'isChild',
        ]),

        propertyRepository() {
            return this.repositoryFactory.create('property_group_option');
        },
    },

    watch: {
        'product.options': {
            handler(value) {
                if (!value) {
                    return;
                }

                this.isInherited = this.isChild && !this.product.options.total;
            },
            immediate: true,
        },
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
            this.propertyRepository.search(new Criteria(1, 1)).then((res) => {
                this.propertiesAvailable = res.total > 0;
            });
        },

        restoreInheritance() {
            this.isInherited = true;
        },

        removeInheritance() {
            this.isInherited = false;
        },
    },
});
