import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

Component.extend('sw-mail-template-create', 'sw-mail-template-detail', {
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.mail.template.create') && !to.params.id) {
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
                this.mailTemplate = this.mailTemplateStore.create(this.$route.params.id);
            }
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.mail.template.detail', params: { id: this.mailTemplate.id } });
        },

        onSave() {
            this.$super.onSave();
        }
    }
});
