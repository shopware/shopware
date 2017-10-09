import utils from 'src/core/service/util.service';
import template from './core-order-detail.html.twig';
import './core-order-detail.less';

export default Shopware.ComponentFactory.register('core-order-detail', {
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
            const uuid = this.$route.params.uuid;

            this.isWorking = true;
            this.orderService.readByUuid(uuid).then((response) => {
                this.notModifiedOrder = { ...response.data };
                this.order = response.data;
                this.isWorking = false;
            });
        },

        getCustomerData() {
            this.customerService.readAll().then((response) => {
                this.customers = response.data;
            });
        },

        getPaymentMethodData() {
            this.paymentMethodService.readAll().then((response) => {
                this.paymentMethods = response.data;
            });
        },

        getCountryData() {
            this.countryService.readAll().then((response) => {
                this.countries = response.data;
            });
        },

        getShopData() {
            this.shopService.readAll().then((response) => {
                this.shops = response.data;
            });
        },

        getCurrencyData() {
            this.currencyService.readAll().then((response) => {
                this.currencies = response.data;
            });
        },

        getOrderStateData() {
            this.orderStateService.readAll().then((response) => {
                this.orderStates = response.data;
            });
        },

        onSaveForm() {
            const uuid = this.$route.params.uuid;
            const changeSet = utils.getObjectChangeSet(this.notModifiedOrder, this.order);

            // Check if we're having categories and apply them to the change set
            // if (this.order.lineItems.length) {
            //     changeSet.lineItems = this.order.lineItems;
            // }

            this.isWorking = true;
            this.orderService.updateByUuid(uuid, changeSet).then((response) => {
                this.notModifiedOrder = { ...response.data };
                this.order = response.data;
                this.isWorking = false;
            });
        }
    },

    template
});
