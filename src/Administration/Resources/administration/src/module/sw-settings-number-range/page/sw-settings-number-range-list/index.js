import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-number-range-list.html.twig';
import './sw-settings-number-range-list.scss';

Component.register('sw-settings-number-range-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'number_range',
            sortBy: 'number_range.name'
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        filters() {
            return [];
        },

        expandButtonClass() {
            return {
                'is--hidden': this.expanded
            };
        },

        collapseButtonClass() {
            return {
                'is--hidden': !this.expanded
            };
        }
    },

    methods: {
        getList() {
            this.isLoading = true;

            const params = this.getListingParams();
            params.associations = {
                type: {},
                numberRangeSalesChannels: {
                    associations: {
                        salesChannel: {}
                    }
                }
            };

            return this.store.getList(params, true).then((response) => {
                this.total = response.total;
                this.items = response.items;
                this.isLoading = false;

                return this.items;
            });
        }
    }
});
