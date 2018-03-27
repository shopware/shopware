import { Component, Mixin } from 'src/core/shopware';
import template from './sw-product-detail.html.twig';
import './sw-product-detail.less';

Component.register('sw-product-detail', {
    inject: ['customerGroupService'],

    mixins: [
        Mixin.getByName('product'),
        Mixin.getByName('manufacturerList'),
        Mixin.getByName('taxList'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            customerGroups: []
        };
    },

    computed: {
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
            this.getCustomerGroupData();
        },

        getCustomerGroupData() {
            this.customerGroupService.getList().then((response) => {
                this.customerGroups = response.data;
            });
        },

        onSave() {
            this.saveProduct();
        }
    },

    template
});
