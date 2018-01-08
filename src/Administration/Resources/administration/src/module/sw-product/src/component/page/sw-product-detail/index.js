import ProductDetailRepository from 'src/core/repository/product.detail.repository';
import template from './sw-product-detail.html.twig';
import './sw-product-detail.less';

Shopware.Component.register('sw-product-detail', {
    inject: ['categoryService', 'productManufacturerService', 'taxService', 'customerGroupService'],

    mixins: [ProductDetailRepository],

    data() {
        return {
            isWorking: false,
            product: {
                attribute: {},
                categories: [],
                prices: []
            },
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
        },

        priceColumns() {
            return [
                { field: 'quantityStart', label: 'Von', type: 'number' },
                { field: 'quantityEnd', label: 'Bis', type: 'number' },
                { field: 'price', label: 'Preis', type: 'number' },
                { field: 'pseudoPrice', label: 'Pseudo Preis', type: 'number' },
                { field: 'customerGroupId', label: 'Kundengruppe', type: 'select', options: this.customerGroupOptions }
            ];
        }
    },

    created() {
        this.initProduct(this.$route.params.id).then((proxy) => {
            this.$emit(
                'core-product-detail:load:after',
                proxy.data
            );
        });
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
            this.isWorking = true;

            this.$emit(
                'core-product-detail:save:before',
                this
            );

            this.saveProduct().then((data) => {
                this.isWorking = false;

                this.$emit(
                    'core-product-detail:save:after',
                    data
                );

                if (!this.$route.params.id && data.id) {
                    this.$router.push({ path: `/core/product/detail/${data.id}` });
                }
            }).catch(() => {
                this.isWorking = false;
            });
        }
    },

    template
});
