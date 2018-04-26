import { Mixin } from 'src/core/shopware';

/**
 * @module app/mixin/customerGroupList
 */
Mixin.register('customerGroupList', {
    data() {
        return {
            customerGroups: [],
            totalCustomerGroups: 0,
            limit: 25,
            total: 0,
            isLoading: false
        };
    },

    mounted() {
        this.getCustomerGroupList();
    },

    methods: {
        getCustomerGroupList() {
            this.isLoading = true;

            return this.$store.dispatch('customerGroup/getCustomerGroupList', this.offset, this.limit).then((response) => {
                this.totalCustomerGroups = response.total;
                this.customerGroups = response.items;
                this.isLoading = false;

                return this.customerGroups;
            });
        }
    }
});
