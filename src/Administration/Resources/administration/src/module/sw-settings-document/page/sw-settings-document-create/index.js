const { Component } = Shopware;
const utils = Shopware.Utils;

Component.extend('sw-settings-document-create', 'sw-settings-document-detail', {

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.document.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.documentConfig = this.documentBaseConfigStore.create(this.$route.params.id);
            } else {
                this.documentConfig = this.documentBaseConfigStore.create();
            }
            this.documentConfig.isLoading = true;
            this.$super('createdComponent');
            this.documentConfig.global = false;
            this.documentConfig.isLoading = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.settings.document.detail', params: { id: this.documentConfig.id } });
        },

        onSave() {
            this.$super('onSave');
        }
    }
});
