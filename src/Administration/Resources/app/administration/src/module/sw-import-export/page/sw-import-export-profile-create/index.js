const { Component } = Shopware;
const utils = Shopware.Utils;

Component.extend('sw-import-export-profile-create', 'sw-import-export-profile-detail', {
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.import.export.profile_create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        loadEntityData() {
            this.repository = this.repositoryFactory.create('import_export_profile');

            this.importExportProfile = this.repository.create(Shopware.Context.api, this.$route.params.id);
        },

        onSave() {
            this.$super('onSave').then(() => {
                this.$router.push({ name: 'sw.import.export.profile_detail', params: { id: this.importExportProfile.id } });
            });
        }
    }
});
