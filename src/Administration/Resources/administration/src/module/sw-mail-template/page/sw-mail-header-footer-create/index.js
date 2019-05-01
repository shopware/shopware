import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

Component.extend('sw-mail-header-footer-create', 'sw-mail-header-footer-detail', {
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.mail.template.create_head_foot') && !to.params.id) {
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
                this.mailHeaderFooter = this.mailHeaderFooterStore.create(this.$route.params.id);
            }
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.mail.template.detail_head_foot', params: { id: this.mailHeaderFooter.id } });
        },

        onSave() {
            this.$super.onSave();
        }
    }
});
