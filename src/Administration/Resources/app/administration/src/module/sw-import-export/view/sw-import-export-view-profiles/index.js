/**
 * @package services-settings
 */
import template from './sw-import-export-view-profiles.html.twig';
import './sw-import-export-view-profiles.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory', 'importExport', 'feature'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            selectedProfile: null,
            profiles: null,
            searchTerm: null,
            sortBy: 'label',
            sortDirection: 'ASC',
            showProfileEditModal: false,
            showNewProfileWizard: false,
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
            const criteria = new Criteria(1, 25);
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
                    property: 'technicalName',
                    dataIndex: 'technicalName',
                    label: 'sw-import-export.profile.technicalNameColumn',
                    allowResize: true,
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
            const profile = this.profileRepository.create();
            profile.fileType = 'text/csv';
            profile.mapping = [];
            profile.config = {};
            profile.config.createEntities = true;
            profile.config.updateEntities = true;
            profile.type = 'import-export';
            profile.translated = {};
            profile.delimiter = ';';
            profile.enclosure = '"';

            this.selectedProfile = null;
            this.selectedProfile = profile;
            this.showNewProfileWizard = true;
        },

        async onEditProfile(id) {
            const profile = await this.profileRepository.get(id);

            if (Array.isArray(profile.config) && profile.config.length <= 0) {
                if (this.isCompatEnabled('INSTANCE_SET')) {
                    this.$set(profile, 'config', {});
                } else {
                    this.profile.config = {};
                }
            }

            if (profile.config?.createEntities === undefined) {
                profile.config.createEntities = true;
            }
            if (profile.config?.updateEntities === undefined) {
                profile.config.updateEntities = true;
            }

            this.selectedProfile = profile;
            this.showProfileEditModal = true;
        },

        onDuplicateProfile(item) {
            const behavior = {
                cloneChildren: false,
                overwrites: {
                    label: `${this.$tc('sw-import-export.profile.copyOfLabel')} ${item.label || item.translated.label}`,
                    technicalName: `${item.technicalName}-copy-${Date.now()}`,
                    systemDefault: false,
                },
            };

            return this.profileRepository.clone(item.id, behavior, Shopware.Context.api).then((clone) => {
                const criteria = new Criteria(1, 25);
                criteria.setIds([clone.id]);
                return this.profileRepository.search(criteria);
            }).then((profiles) => {
                const profile = profiles[0];
                if (profile.config?.createEntities === undefined) {
                    profile.config.createEntities = true;
                }
                if (profile.config?.updateEntities === undefined) {
                    profile.config.updateEntities = true;
                }

                this.selectedProfile = profile;
                this.showProfileEditModal = true;
                return this.loadProfiles(); // refresh the list in any case (even if the modal is canceled)
                // because the duplicate already exists.
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                });
            });
        },

        async onDownloadTemplate(profile) {
            return window.open(await this.importExport.getTemplateFileDownloadUrl(profile.id), '_blank');
        },

        onDeleteProfile(id) {
            this.$refs.listing.showDelete(id);
        },

        closeSelectedProfile() {
            this.showProfileEditModal = false;
            this.selectedProfile = null;
        },

        saveSelectedProfile() {
            this.isLoading = true;
            return this.profileRepository.save(this.selectedProfile, Shopware.Context.api).then(() => {
                this.showProfileEditModal = false;
                this.selectedProfile = null;
                this.onCloseNewProfileWizard();
                this.createNotificationSuccess({
                    message: this.$tc('sw-import-export.profile.messageSaveSuccess', 0),
                });
                return this.loadProfiles();
            }).catch((exception) => {
                this.onError(exception);
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onError(error) {
            const errorCode = error?.response?.data?.errors?.[0]?.code ?? null;
            let errorDetails = '';

            if (errorCode !== null && this.$te(`sw-import-export.errors.${errorCode}`)) {
                errorDetails = this.$tc(`sw-import-export.errors.${errorCode}`);
            }

            this.createNotificationError({
                message: `${this.$tc('sw-import-export.profile.messageSaveError', 0)} ${errorDetails}`,
            });
        },

        getTypeLabel(isSystemDefault) {
            return isSystemDefault ?
                this.$tc('sw-import-export.profile.defaultTypeLabel') :
                this.$tc('sw-import-export.profile.customTypeLabel');
        },

        onCloseNewProfileWizard() {
            this.showNewProfileWizard = false;
            this.selectedProfile = null;
        },
    },
};
