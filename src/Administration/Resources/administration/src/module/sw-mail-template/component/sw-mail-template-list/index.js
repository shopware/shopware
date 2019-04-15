import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-mail-template-list.html.twig';

Component.register('sw-mail-template-list', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            mailTemplates: [],
            showDeleteModal: null,
            isLoading: false
        };
    },

    computed: {
        mailTemplateStore() {
            return State.getStore('mail_template');
        }
    },

    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();
            params.associations = {
                salesChannels: {}
            };

            this.mailTemplates = [];
            this.mailTemplateStore.getList(params, true).then((response) => {
                this.total = response.total;
                this.mailTemplates = response.items;
                this.isLoading = false;

                return this.mailTemplates;
            });
        },

        onEdit(mailTemplate) {
            if (mailTemplate && mailTemplate.id) {
                this.$router.push({
                    name: 'sw.mail.template.detail',
                    params: {
                        id: mailTemplate.id
                    }
                });
            }
        },

        onChangeLanguage(languageId) {
            this.getList(languageId);
        },

        onDeleteMailTemplate(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = null;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.mailTemplateStore.store[id].delete(true).then(() => {
                this.getList();
            });
        },

        onDuplicate(id) {
            this.mailTemplateStore.apiService.clone(id).then((mailTemplate) => {
                this.$router.push(
                    {
                        name: 'sw.mail.template.detail',
                        params: { id: mailTemplate.id }
                    }
                );
            });
        }
    }
});
