import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-property-list.html.twig';

Component.register('sw-property-list', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            properties: [],
            isLoading: false,
            showDeleteModal: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        propertiesStore() {
            return State.getStore('property_group');
        }
    },

    methods: {
        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.propertiesStore.getById(id).delete(true).then(() => {
                this.getList();
            });
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.properties = [];

            params.associations = {
                options: {
                    page: 1,
                    limit: 5
                }
            };

            return this.propertiesStore.getList(params, true).then((response) => {
                this.total = response.total;
                this.properties = response.items;
                this.isLoading = false;

                return this.properties;
            });
        }
    }
});
