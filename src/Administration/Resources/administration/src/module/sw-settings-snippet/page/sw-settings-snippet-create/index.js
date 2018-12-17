import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

Component.extend('sw-settings-snippet-create', 'sw-settings-snippet-detail', {
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.snippet.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.snippetStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();
        },

        onSave() {
            const titleCreateError = this.$tc('sw-settings-snippet.detail.titleCreateError');
            const messageCreateError = this.$tc('sw-settings-snippet.detail.messageCreateError');

            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.settings.snippet.detail', params: { id: this.snippet.id } });
            }).catch(() => {
                this.createNotificationError({
                    title: titleCreateError,
                    message: messageCreateError
                });
            });
        }
    }
});
