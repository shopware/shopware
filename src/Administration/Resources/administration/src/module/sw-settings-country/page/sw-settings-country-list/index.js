import { Component, State } from 'src/core/shopware';
import template from './sw-settings-country-list.html.twig';

Component.register('sw-settings-country-list', {
    template,

    mixins: [
        'listing',
        'notification'
    ],

    data() {
        return {
            country: [],
            isLoading: false,
            showDeleteModal: false
        };
    },

    computed: {
        countryStore() {
            return State.getStore('country');
        }
    },

    created() {
        this.$root.$on('search', this.onSearch);
    },

    destroyed() {
        this.$root.$off('search', this.onSearch);
    },

    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.countrys = [];

            return this.countryStore.getList(params).then((response) => {
                this.total = response.total;
                this.countrys = response.items;
                this.isLoading = false;

                return this.countrys;
            });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            const country = this.countryStore.store[id];
            const countryName = country.name;
            const titleSaveSuccess = this.$tc('sw-settings-country.list.titleDeleteSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-country.list.messageDeleteSuccess', 0, {
                name: countryName
            });

            return this.countryStore.store[id].delete(true).then(() => {
                this.showDeleteModal = false;

                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });

                this.getList();
            });
        }
    }
});
