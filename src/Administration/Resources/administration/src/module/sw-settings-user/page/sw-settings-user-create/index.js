import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-settings-user-create.html.twig';

Component.extend('sw-settings-user-create', 'sw-settings-user-detail', {
    template,

    methods: {
        createdComponent() {
            this.user = this.userStore.create(utils.createId());

            this.userService.getUser().then((response) => {
                this.currentUser = response.data;
            });
        },

        onSave() {
            if (!this.user.localeId) {
                this.user.localeId = this.currentUser.localeId;
            }
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.settings.user.detail', params: { id: this.user.id } });
            });
        }
    }
});
