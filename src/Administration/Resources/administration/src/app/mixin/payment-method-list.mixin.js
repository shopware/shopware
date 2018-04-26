import { Mixin } from 'src/core/shopware';

/**
 * @module app/mixin/paymentMethodList
 */
Mixin.register('paymentMethodList', {
    data() {
        return {
            paymentMethods: [],
            totalPaymentMethods: 0,
            limit: 25,
            total: 0,
            isLoading: false
        };
    },

    mounted() {
        this.getPaymentMethodList();
    },

    methods: {
        getPaymentMethodList() {
            this.isLoading = true;

            return this.$store.dispatch('paymentMethod/getPaymentMethodList', this.offset, this.limit).then((response) => {
                this.totalPaymentMethods = response.total;
                this.paymentMethods = response.items;
                this.isLoading = false;

                return this.paymentMethods;
            });
        }
    }
});
