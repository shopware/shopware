import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-mail-header-footer-list.html.twig';

Component.register('sw-mail-header-footer-list', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            mailHeaderFooters: [],
            showDeleteModal: null,
            isLoading: false
        };
    },

    computed: {
        mailHeaderFooterStore() {
            return State.getStore('mail_header_footer');
        }
    },

    methods: {
        onEdit(mailHeaderFooter) {
            if (mailHeaderFooter && mailHeaderFooter.id) {
                this.$router.push({
                    name: 'sw.mail.template.detail_head_foot',
                    params: {
                        id: mailHeaderFooter.id
                    }
                });
            }
        },

        onDeleteMailHeaderFooter(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = null;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = null;

            return this.mailHeaderFooterStore.store[id].delete(true).then(() => {
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

            params.associations = {
                salesChannels: {
                    page: 1,
                    limit: 5
                }
            };

            this.mailHeaderFooters = [];

            return this.mailHeaderFooterStore.getList(params, true).then((response) => {
                this.total = response.total;
                this.mailHeaderFooters = response.items;
                this.isLoading = false;

                return this.mailHeaderFooters;
            });
        },

        onDuplicate(id) {
            this.mailHeaderFooterStore.apiService.clone(id).then((mailHeaderFooter) => {
                this.$router.push(
                    {
                        name: 'sw.mail.template.detail_head_foot',
                        params: { id: mailHeaderFooter.id }
                    }
                );
            });
        }

    }
});
