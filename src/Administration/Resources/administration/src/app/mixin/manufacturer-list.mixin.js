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
            sortBy: null,
            sortDirection: 'ASC',
            isLoading: false
        };
    },

    mounted() {
        this.getManufacturerList();
    },

    methods: {
        getListingParams() {
            const params = {
                limit: this.limit,
                offset: this.offset
            };

            if (this.term && this.term.length) {
                params.term = this.term;
            }

            if (this.sortBy && this.sortBy.length) {
                params.sortBy = this.sortBy;
                params.sortDirection = this.sortDirection;
            }

            return params;
        },

        getManufacturerList() {
            this.isLoading = true;

            const params = this.getListingParams();

            this.manufacturers = [];
            return this.$store.dispatch('manufacturer/getManufacturerList', params).then((response) => {
                this.totalManufacturers = response.total;
                this.manufacturers = response.manufacturers;
                this.isLoading = false;

                return this.manufacturers;
            });
        }
    }
});
