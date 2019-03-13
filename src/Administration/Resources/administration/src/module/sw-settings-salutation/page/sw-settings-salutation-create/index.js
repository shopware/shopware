import { Component, State } from 'src/core/shopware';
import template from './sw-settings-salutation-create.html.twig';

Component.extend('sw-settings-salutation-create', 'sw-settings-salutation-detail', {
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
            this.salutation = this.salutationStore.create();
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.settings.salutation.detail', params: { id: this.salutation.id } });
            });
        }
    }
});
