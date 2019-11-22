import template from './sw-settings-document-list.html.twig';
import './sw-settings-document-list.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-settings-document-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'document_base_config',
            sortBy: 'document_base_config.name'
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
                documentType: {},
                salesChannels: {
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
