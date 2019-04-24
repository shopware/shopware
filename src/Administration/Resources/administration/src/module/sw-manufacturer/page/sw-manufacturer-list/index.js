import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-manufacturer-list.html.twig';

Component.register('sw-manufacturer-list', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            manufacturers: [],
            showDeleteModal: false,
            isLoading: false,
            entityName: 'manufacturer',
            sortBy: 'name'
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        manufacturerStore() {
            return State.getStore('product_manufacturer');
        }
    },

    methods: {
        onInlineEditSave(manufacturer) {
            this.isLoading = true;

            return manufacturer.save().then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onChangeLanguage(languageId) {
            this.getList(languageId);
        },

        onDeleteManufacturer(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.manufacturerStore.store[id].delete(true).then(() => {
                this.getList();
            });
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            // Default sorting
            if (!params.sortBy && !params.sortDirection) {
                params.sortBy = 'name';
                params.sortDirection = 'ASC';
            }

            this.manufacturers = [];

            return this.manufacturerStore.getList(params).then((response) => {
                this.total = response.total;
                this.manufacturers = response.items;
                this.isLoading = false;

                return this.manufacturers;
            });
        }
    }
});
