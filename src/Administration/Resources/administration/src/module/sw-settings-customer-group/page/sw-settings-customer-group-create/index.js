import { Component, State } from 'src/core/shopware';
import template from './sw-settings-customer-group-create.html.twig';

Component.extend('sw-settings-customer-group-create', 'sw-settings-customer-group-detail', {
    template,

    computed: {
        languageStore() {
            return State.getStore('language');
        }
    },

    methods: {
        createdComponent() {
            if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }
            this.customerGroup = this.customerGroupStore.create();
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.settings.customer.group.detail', params: { id: this.customerGroup.id } });
            });
        }
    }
});
