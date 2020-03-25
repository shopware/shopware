import template from './sw-settings-import-export-view-profiles.html.twig';
import './sw-settings-import-export-view-profiles.scss';

const { Mixin } = Shopware;

Shopware.Component.register('sw-settings-import-export-view-profiles', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            isLoading: false,
            selectedProfile: null,
            profiles: null,
            searchTerm: null
        };
    },

    computed: {
        profileRepository() {
            return this.repositoryFactory.create('import_export_profile');
        },

        profileCriteria() {
            const criteria = new Shopware.Data.Criteria();

            criteria.setPage(1);
            criteria.setTerm(this.searchTerm);
            criteria.addAssociation('importExportLogs');

            return criteria;
        },

        profilesColumns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: 'Name',
                    allowResize: true,
                    primary: true
                },
                {
                    property: 'systemDefault',
                    dataIndex: 'systemDefault',
                    label: 'sw-settings-import-export.profile.sourceEntityLabel',
                    align: 'center',
                    allowResize: true
                }
            ];
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadProfiles();
        },

        async loadProfiles() {
            this.isLoading = true;

            this.profiles = await this.profileRepository.search(this.profileCriteria, Shopware.Context.api);

            this.isLoading = false;
        },

        onSearch() {
            this.loadProfiles();
        },

        onAddNewProfile() {
            this.selectedProfile = this.profileRepository.create(Shopware.Context.api);
            this.selectedProfile.fileType = 'text/csv';
            this.selectedProfile.mapping = [];
            this.selectedProfile.delimiter = ';';
            this.selectedProfile.enclosure = '"';
        },

        async onEditProfile(id) {
            this.selectedProfile = await this.profileRepository.get(id, Shopware.Context.api);
        },

        onDuplicateProfile(item) {
            const clone = this.profileRepository.create(Shopware.Context.api);

            clone.name = `${this.$tc('sw-settings-import-export.profile.copyOfLabel')} ${item.name}`;
            clone.systemDefault = false;
            clone.fileType = item.fileType;
            clone.mapping = item.mapping;
            clone.delimiter = item.delimiter;
            clone.enclosure = item.enclosure;
            clone.sourceEntity = item.sourceEntity;

            this.profileRepository.save(clone, Shopware.Context.api).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-import-export.profile.titleSaveSuccess'),
                    message: this.$tc('sw-settings-import-export.profile.messageSaveSuccess', 0)
                });
                return this.loadProfiles();
            }).then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-import-export.profile.titleSaveError'),
                    message: this.$tc('sw-settings-import-export.profile.messageSaveError', 0)
                });
            });
        },

        onDeleteProfile(id) {
            this.isLoading = true;
            this.profileRepository.delete(id, Shopware.Context.api).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-import-export.profile.titleDeleteSuccess'),
                    message: this.$tc('sw-settings-import-export.profile.messageDeleteSuccess', 0)
                });
                this.loadProfiles();
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-import-export.profile.titleDeleteError'),
                    message: this.$tc('sw-settings-import-export.profile.messageDeleteError', 0)
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        closeSelectedProfile() {
            this.selectedProfile = null;
        },

        saveSelectedProfile() {
            this.isLoading = true;
            this.profileRepository.save(this.selectedProfile, Shopware.Context.api).then(() => {
                this.selectedProfile = null;
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-import-export.profile.titleSaveSuccess'),
                    message: this.$tc('sw-settings-import-export.profile.messageSaveSuccess', 0)
                });
                return this.loadProfiles();
            }).then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-import-export.profile.titleSaveError'),
                    message: this.$tc('sw-settings-import-export.profile.messageSaveError', 0)
                });
            });
        }
    }
});
