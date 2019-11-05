import template from './sw-import-export-profile-detail.html.twig';

const { Component, Mixin } = Shopware;
const { warn } = Shopware.Utils.debug;

Component.register('sw-import-export-profile-detail', {
    template,

    inject: ['repositoryFactory', 'importExportService'],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            importExportProfile: false,
            importExportProfileId: null,
            isLoading: false,
            selectedItem: null,
            isSaveSuccessful: false,
            features: {
                entities: [],
                fileTypes: []
            }
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadFeatures();
            if (this.$route.params.id) {
                this.loadEntityData();
            }
        },

        loadEntityData() {
            this.isLoading = true;
            this.repository = this.repositoryFactory.create('import_export_profile');

            this.repository
                .get(this.$route.params.id, Shopware.Context.api)
                .then((importExportProfile) => {
                    this.isLoading = false;
                    this.importExportProfile = importExportProfile;
                });
        },

        loadFeatures() {
            this.importExportService.getFeatures().then((response) => {
                this.features = response;
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;
            const importExportProfileName = this.importExportProfile.name;

            const notificationSaveError = {
                title: this.$tc('global.default.error'),
                message: this.$tc(
                    'global.notification.notificationSaveErrorMessage', 0, { name: importExportProfileName }
                )
            };

            return this.repository.save(this.importExportProfile, Shopware.Context.api).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((exception) => {
                this.isLoading = false;
                this.createNotificationError(notificationSaveError);
                warn(this._name, exception.message, exception.response);
            });
        },

        translateEntity(name) {
            return this.$tc(`global.entities.${name}`);
        },

        onOpenCsvMappingModal() {
            this.selectedItem = this.importExportProfile;
        },

        formatFileType(mimeType) {
            const parts = mimeType.split('/');
            return parts.length > 1 ? parts[1].toUpperCase() : mimeType;
        }
    }
});
