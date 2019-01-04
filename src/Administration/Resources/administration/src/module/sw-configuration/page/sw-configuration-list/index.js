import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-configuration-list.html.twig';

Component.register('sw-configuration-list', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            orders: [],
            isLoading: false,
            showDeleteModal: false
        };
    },

    computed: {
        configurationStore() {
            return State.getStore('configuration_group');
        }
    },

    methods: {
        onEdit(configuration) {
            if (configuration && configuration.id) {
                this.$router.push({
                    name: 'sw.configuration.detail',
                    params: {
                        id: configuration.id
                    }
                });
            }
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.configurationStore.getById(id).delete(true).then(() => {
                this.getList();
            });
        },

        onInlineEditSave(configuration) {
            this.isLoading = true;

            configuration.save().then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.configurations = [];

            params.associations = {
                options: {
                    page: 1,
                    limit: 5
                }
            };

            return this.configurationStore.getList(params, true).then((response) => {
                this.total = response.total;
                this.configurations = response.items;
                this.isLoading = false;

                return this.configurations;
            });
        }
    }
});
