import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-settings-language-create.html.twig';

Component.extend('sw-settings-language-create', 'sw-settings-language-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.language.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }

            if (this.$route.params.id) {
                this.languageStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.settings.language.detail', params: { id: this.language.id } });
            });
        }
    }
});
