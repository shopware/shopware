import { Mixin } from 'src/core/shopware';

/**
 * @module app/mixin/manufacturerList
 */
Mixin.register('manufacturerList', {
    data() {
        return {
            manufacturers: [],
            totalManufacturers: 0,
            limit: 25,
            total: 0,
            isLoading: false
        };
    },

    mounted() {
        this.getManufacturerList();
    },

    methods: {
        getManufacturerList() {
            this.isLoading = true;

            return this.$store.dispatch('manufacturer/getManufacturerList', this.offset, this.limit).then((response) => {
                this.totalManufacturers = response.total;
                this.manufacturers = response.manufacturers;
                this.isLoading = false;

                return this.manufacturers;
            });
        }
    }
});
