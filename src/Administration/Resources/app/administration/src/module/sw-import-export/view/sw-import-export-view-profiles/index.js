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
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            selectedProfile: null,
            profiles: null,
            searchTerm: null,
            sortBy: 'name',
            sortDirection: 'ASC',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
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
                    primary: true,
                },
                {
                    property: 'systemDefault',
                    dataIndex: 'systemDefault',
                    label: 'sw-import-export.profile.typeColumn',
                    allowResize: true,
                },
            ];
        },

        isNotSystemLanguage() {
            return Shopware.Context.api.systemLanguageId !== Shopware.Context.api.languageId;
        },

        createTooltip() {
            return {
                showDelay: 300,
                message: this.$tc('sw-import-export.profile.addNewProfileTooltipLanguage'),
                disabled: !this.isNotSystemLanguage,
            };
        },
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

            this.profiles = await this.profileRepository.search(this.profileCriteria);

            this.isLoading = false;
        },

        reloadContent() {
            this.loadProfiles();
        },

        onSearch() {
            this.loadProfiles();
        },

        onAddNewProfile() {
            this.selectedProfile = this.profileRepository.create();
            this.selectedProfile.fileType = 'text/csv';
            this.selectedProfile.mapping = [];
            this.$set(this.selectedProfile, 'config', {});
            this.$set(this.selectedProfile, 'translated', {});
            this.selectedProfile.delimiter = ';';
            this.selectedProfile.enclosure = '"';
        },

        async onEditProfile(id) {
            this.selectedProfile = await this.profileRepository.get(id);

            if (Array.isArray(this.selectedProfile.config) && this.selectedProfile.config.length <= 0) {
                this.$set(this.selectedProfile, 'config', {});
            }
        },

        onDuplicateProfile(item) {
            const behavior = {
                cloneChildren: false,
                overwrites: {
                    label: `${this.$tc('sw-import-export.profile.copyOfLabel')} ${item.label || item.translated.label}`,
                    systemDefault: false,
                },
            };

            return this.profileRepository.clone(item.id, Shopware.Context.api, behavior).then((clone) => {
                const criteria = new Criteria();
                criteria.setIds([clone.id]);
                return this.profileRepository.search(criteria);
            }).then((profiles) => {
                this.selectedProfile = profiles[0];
                return this.loadProfiles(); // refresh the list in any case (even if the modal is canceled)
                // because the duplicate already exists.
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                });
            });
        },

        onDeleteProfile(id) {
            this.$refs.listing.showDelete(id);
        },

        closeSelectedProfile() {
            this.selectedProfile = null;
        },

        saveSelectedProfile() {
            this.isLoading = true;
            return this.profileRepository.save(this.selectedProfile, Shopware.Context.api).then(() => {
                this.selectedProfile = null;
                this.createNotificationSuccess({
                    message: this.$tc('sw-import-export.profile.messageSaveSuccess', 0),
                });
                return this.loadProfiles();
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-import-export.profile.messageSaveError', 0),
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        getTypeLabel(isSystemDefault) {
            return isSystemDefault ?
                this.$tc('sw-import-export.profile.defaultTypeLabel') :
                this.$tc('sw-import-export.profile.customTypeLabel');
        },
    },
});
