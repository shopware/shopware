import template from './sw-import-export-importer.html.twig';
import './sw-import-export-importer.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
Shopware.Component.register('sw-import-export-importer', {
    template,

    inject: ['importExport', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        sourceEntity: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            selectedProfileId: null,
            selectedProfile: null,
            config: {},
            progressOffset: 0,
            progressTotal: null,
            progressText: '',
            progressState: '',
            progressLogEntry: null,
            isLoading: false,
            importFile: null,
            importModalProfile: null,
        };
    },

    computed: {
        profileCriteria() {
            const criteria = new Criteria();

            if (this.sourceEntity.length > 0) {
                criteria.addFilter(
                    Criteria.equals('sourceEntity', this.sourceEntity),
                );
            }

            return criteria;
        },

        logRepository() {
            return this.repositoryFactory.create('import_export_log');
        },

        disableImporting() {
            return this.isLoading || this.selectedProfileId === null || this.importFile === null;
        },

        showProductVariantsInfo() {
            return this.selectedProfile &&
                this.selectedProfile.sourceEntity === 'product' &&
                this.config &&
                this.config.includeVariants;
        },

        logCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('invalidRecordsLog');
            criteria.addAssociation('file');

            return criteria;
        },
    },

    watch: {
        importFile: {
            handler(newValue) {
                if (newValue) {
                    this.resetProgressStats();
                }
            },
        },
    },

    methods: {
        onProfileSelect(profileId, profile) {
            this.selectedProfileId = profileId;
            this.selectedProfile = profile;
        },

        resetProgressStats() {
            // Reset progress stats
            this.progressOffset = 0;
            this.progressTotal = null;
            this.progressText = '';
            this.progressState = '';
            this.progressLogEntry = null;
        },

        onStartProcess() {
            this.isLoading = true;

            this.resetProgressStats();
            this.progressTotal = 0;

            const profile = this.selectedProfileId;

            this.importExport.import(profile, this.importFile, this.handleProgress, this.config).then((result) => {
                const logEntry = result.data.log;
                this.importFile = null;

                this.logRepository.get(logEntry.id, Shopware.Context.api, this.logCriteria).then((entry) => {
                    this.progressLogEntry = entry;
                });
            }).catch((error) => {
                if (!error.response || !error.response.data || !error.response.data.errors) {
                    this.createNotificationError({
                        message: error.message,
                    });
                } else {
                    error.response.data.errors.forEach((singleError) => {
                        this.createNotificationError({
                            message: `${singleError.code}: ${singleError.detail}`,
                        });
                    });
                }

                this.resetProgressStats();
                this.isLoading = false;
            });
        },

        handleProgress(progress) {
            this.progressOffset = Math.round(progress.offset / 1024); // Convert byte to kilobyte
            this.progressTotal = Math.round(progress.total / 1024); // Convert byte to kilobyte
            this.progressState = progress.state;

            if (progress.state === 'succeeded') {
                this.createNotificationSuccess({
                    message: this.$tc('sw-import-export.importer.messageImportSuccess', 0),
                });
                this.onProgressFinished();
            } else if (progress.state === 'failed') {
                this.createNotificationError({
                    message: this.$tc('sw-import-export.importer.messageImportError', 0),
                });
                this.onProgressFinished();
            }
        },

        onProgressFinished() {
            this.isLoading = false;
            this.$emit('import-finish');
        },

        setImportModalProfile(profileName) {
            this.importModalProfile = profileName;
        },
    },
});
