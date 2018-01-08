import utils from 'src/core/service/util.service';
import template from './sw-order-detail.html.twig';
import './sw-order-detail.less';

Shopware.Component.register('sw-order-detail', {
    inject: [
        'orderService',
        'customerService',
        'shopService',
        'orderStateService',
        'currencyService',
        'countryService',
        'paymentMethodService',
        'countryService'
    ],

    data() {
        return {
            isWorking: false,
            order: {
                customer: {},
                currency: {},
                billingAddress: {},
                paymentMethod: {},
                lineItems: [],
                deliveries: [],
                // date: {},
                state: {}
            },
            customers: [],
            countries: [],
            shops: [],
            currencies: [],
            orderStates: [],
            paymentMethods: [],
            notModifiedOrder: {}
        };
    },

    created() {
        this.getData();
    },

    watch: {
        $route: 'getData'
    },

    methods: {

        getData() {
            this.getOrderData();
            this.getCustomerData();
            this.getPaymentMethodData();
            this.getCountryData();
            this.getShopData();
            this.getCurrencyData();
            this.getOrderStateData();
        },

        getOrderData() {
            const id = this.$route.params.id;

            this.isWorking = true;
            this.orderService.getById(id).then((response) => {
                this.notModifiedOrder = { ...response.data };
                this.order = response.data;
                this.isWorking = false;
            });
        },

        getCustomerData() {
            this.customerService.getList().then((response) => {
                this.customers = response.data;
            });
        },

        getPaymentMethodData() {
            this.paymentMethodService.getList().then((response) => {
                this.paymentMethods = response.data;
            });
        },

        getCountryData() {
            this.countryService.getList().then((response) => {
                this.countries = response.data;
            });
        },

        getShopData() {
            this.shopService.getList().then((response) => {
                this.shops = response.data;
            });
        },

        getCurrencyData() {
            this.currencyService.getList().then((response) => {
                this.currencies = response.data;
            });
        },

        getOrderStateData() {
            this.orderStateService.getList().then((response) => {
                this.orderStates = response.data;
            });
        },

        onSaveForm() {
            const id = this.$route.params.id;
            const changeSet = utils.getObjectChangeSet(this.notModifiedOrder, this.order);

            // Check if we're having categories and apply them to the change set
            // if (this.order.lineItems.length) {
            //     changeSet.lineItems = this.order.lineItems;
            // }

            this.isWorking = true;
            this.orderService.updateById(id, changeSet).then((response) => {
                this.notModifiedOrder = { ...response.data };
                this.order = response.data;
                this.isWorking = false;
            });
        }
    },

    template
});
