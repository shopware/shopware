import { Component, Mixin } from 'src/core/shopware';
import template from './sw-product-detail.html.twig';
import './sw-product-detail.less';

Component.register('sw-product-detail', {
    inject: ['categoryService', 'productManufacturerService', 'taxService', 'customerGroupService'],

    mixins: [
        Mixin.getByName('product'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            taxRates: [],
            manufacturers: [],
            customerGroups: [],
            formTest: {
                text: 'This is my text',
                text2: 'This is my second text',
                textArea: 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam.',
                checkbox: false,
                checkbox2: true,
                select: 2,
                date: '2018-02-13T22:30',
                number: 10,
                email: 'psc@shopware.com',
                radio: null,
                switchField: true
            }
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
        }
    },

    template
});
