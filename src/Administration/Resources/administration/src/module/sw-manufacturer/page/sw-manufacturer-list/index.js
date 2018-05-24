import { Component, Mixin } from 'src/core/shopware';
import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import template from './sw-manufacturer-list.html.twig';

Component.register('sw-manufacturer-list', {
    template,

    mixins: [
        PaginationMixin,
        Mixin.getByName('manufacturerList')
    ],

    methods: {
        updateRoute() {
            const params = this.getListingParams();

            this.$router.push({
                name: 'sw.manufacturer.index',
                params
            });
        },

        handlePagination() {
            this.updateRoute();
            this.getManufacturerList();
        },

        onSortColumn(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = (this.sortDirection === 'ASC' ? 'DESC' : 'ASC');
            } else {
                this.sortBy = column.dataIndex;
                this.sortDirection = 'ASC';
            }

            this.updateRoute();
            this.getManufacturerList();
        },

        onRefresh() {
            this.getManufacturerList();
        },

        onInlineEditSave(opts) {
            this.isLoading = true;

            return this.$store.dispatch('manufacturer/saveManufacturer', opts.item).then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        }
    }
});
