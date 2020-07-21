import template from './sw-import-export-view-profiles.html.twig';
import './sw-import-export-view-profiles.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
Shopware.Component.register('sw-import-export-view-profiles', {
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
            searchTerm: null,
            sortBy: 'name',
            sortDirection: 'ASC'
        };
    },

    computed: {
        profileRepository() {
            return this.repositoryFactory.create('import_export_profile');
        },

        profileCriteria() {
            const criteria = new Criteria();

            criteria.setPage(1);
            criteria.setTerm(this.searchTerm);
            criteria.addAssociation('importExportLogs');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return criteria;
        },

        profilesColumns() {
            return [
                {
                    property: 'label',
                    dataIndex: 'label',
                    label: 'sw-import-export.profile.nameColumn',
                    allowResize: true,
                    primary: true
                },
                {
                    property: 'systemDefault',
                    dataIndex: 'systemDefault',
                    label: 'sw-import-export.profile.typeColumn',
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

        reloadContent() {
            this.loadProfiles();
        },

        onSearch() {
            this.loadProfiles();
        },

        onAddNewProfile() {
            this.selectedProfile = this.profileRepository.create(Shopware.Context.api);
            this.selectedProfile.fileType = 'text/csv';
            this.selectedProfile.mapping = [];
            this.$set(this.selectedProfile, 'config', {});
            this.$set(this.selectedProfile, 'translated', {});
            this.selectedProfile.delimiter = ';';
            this.selectedProfile.enclosure = '"';
        },

        async onEditProfile(id) {
            this.selectedProfile = await this.profileRepository.get(id, Shopware.Context.api);

            if (Array.isArray(this.selectedProfile.config) && this.selectedProfile.config.length <= 0) {
                this.$set(this.selectedProfile, 'config', {});
            }
        },

        onDuplicateProfile(item) {
            this.selectedProfile = this.profileRepository.create(Shopware.Context.api);

            this.selectedProfile.label = `${this.$tc('sw-import-export.profile.copyOfLabel')} ${item.label || ''}`;
            this.$set(this.selectedProfile, 'translated', {});
            this.selectedProfile.systemDefault = false;
            this.$set(this.selectedProfile, 'config', Array.isArray(item.config) ? {} : item.config);
            this.selectedProfile.fileType = item.fileType;
            this.selectedProfile.mapping = item.mapping;
            this.selectedProfile.delimiter = item.delimiter;
            this.selectedProfile.enclosure = item.enclosure;
            this.selectedProfile.sourceEntity = item.sourceEntity;
        },

        onDeleteProfile(id) {
            this.$refs.listing.showDelete(id);
        },

        closeSelectedProfile() {
            this.selectedProfile = null;
        },

        saveSelectedProfile() {
            this.isLoading = true;
            this.profileRepository.save(this.selectedProfile, Shopware.Context.api).then(() => {
                this.selectedProfile = null;
                this.createNotificationSuccess({
                    title: this.$tc('global.default.success'),
                    message: this.$tc('sw-import-export.profile.messageSaveSuccess', 0)
                });
                return this.loadProfiles();
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-import-export.profile.messageSaveError', 0)
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        getTypeLabel(isSystemDefault) {
            return isSystemDefault ?
                this.$tc('sw-import-export.profile.defaultTypeLabel') :
                this.$tc('sw-import-export.profile.customTypeLabel');
        }
    }
});
