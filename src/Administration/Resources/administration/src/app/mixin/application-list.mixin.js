import { Mixin } from 'src/core/shopware';

/**
 * @module app/mixin/applicationList
 */
Mixin.register('applicationList', {
    data() {
        return {
            applications: [],
            totalApplications: 0,
            limit: 25,
            total: 0,
            isLoading: false
        };
    },

    mounted() {
        this.getApplicationList();
    },

    methods: {
        getApplicationList() {
            this.isLoading = true;

            return this.$store.dispatch('application/getApplicationList', this.offset, this.limit).then((response) => {
                this.totalApplications = response.total;
                this.applications = response.items;
                this.isLoading = false;

                return this.applications;
            });
        }
    }
});
