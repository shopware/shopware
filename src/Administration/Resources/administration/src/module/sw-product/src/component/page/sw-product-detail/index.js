import { Component, Mixin } from 'src/core/shopware';
import template from './sw-product-detail.html.twig';
import './sw-product-detail.less';

Component.register('sw-product-detail', {
    inject: ['categoryService', 'productManufacturerService', 'taxService', 'customerGroupService'],

    mixins: [
        Mixin.getByName('product')
    ],

    data() {
        return {
            taxRates: [],
            manufacturers: [],
            customerGroups: []
        };
    },

    computed: {
        categoryService() {
            return this.categoryService;
        },

        customerGroupOptions() {
            const options = [];

            this.customerGroups.forEach((item) => {
                options.push({
                    value: item.id,
                    label: item.name
                });
            });

            return options;
        }
    },

    created() {
        if (this.$route.params.id) {
            this.productId = this.$route.params.id;
        }

        this.getData();
    },

    watch: {
        $route: 'getData'
    },

    methods: {
        getData() {
            this.getManufacturerData();
            this.getCustomerGroupData();
            this.getTaxData();
        },

        getManufacturerData() {
            this.productManufacturerService.getList().then((response) => {
                this.manufacturers = response.data;
            });
        },

        getTaxData() {
            this.taxService.getList().then((response) => {
                this.taxRates = response.data;
            });
        },

        getCustomerGroupData() {
            this.customerGroupService.getList().then((response) => {
                this.customerGroups = response.data;
            });
        },

        onSave() {
            this.saveProduct();
        },

        /**
         * Todo: Remove test notifications
         */
        addNotificationInfo() {
            this.$store.dispatch('notification/createNotification', {
                title: 'Info',
                variant: 'info',
                text: 'Lorem ipsum dolor sit amet.'
            });
        },

        addNotificationError() {
            this.$store.dispatch('notification/createNotification', {
                title: 'Error',
                variant: 'error',
                text: 'Lorem ipsum dolor sit amet.'
            });
        },

        addNotificationSuccess() {
            this.$store.dispatch('notification/createNotification', {
                title: 'Success',
                variant: 'success',
                text: 'Lorem ipsum dolor sit amet.'
            });
        },

        addNotificationWarning() {
            this.$store.dispatch('notification/createNotification', {
                title: 'Warning',
                variant: 'warning',
                text: 'Lorem ipsum dolor sit amet.'
            });
        },

        addSystemNotificationError() {
            this.$store.dispatch('notification/createNotification', {
                title: 'Shopware Error',
                variant: 'error',
                system: true,
                text: 'Lorem ipsum dolor sit amet.'
            });
        },

        addSystemNotificationSuccess() {
            this.$store.dispatch('notification/createNotification', {
                title: 'Shopware Success',
                variant: 'success',
                system: true,
                text: 'Lorem ipsum dolor sit amet.'
            });
        },

        addSystemNotificationInfo() {
            this.$store.dispatch('notification/createNotification', {
                title: 'Shopware Info',
                variant: 'info',
                system: true,
                text: 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut.'
            });
        },

        addSystemNotificationWarning() {
            this.$store.dispatch('notification/createNotification', {
                title: 'Shopware Warning',
                variant: 'warning',
                text: 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut.',
                system: true
            });
        }
    },

    template
});
