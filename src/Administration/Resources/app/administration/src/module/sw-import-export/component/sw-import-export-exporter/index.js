import template from './sw-import-export-exporter.html.twig';
import './sw-import-export-exporter.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
Shopware.Component.register('sw-import-export-exporter', {
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
            config: {
                parameters: {},
            },
            progressOffset: 0,
            progressTotal: null,
            progressText: '',
            progressState: '',
            progressLogEntry: null,
            isLoading: false,
            exportModalProfile: null,
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

        disableExporting() {
            return this.isLoading || this.selectedProfileId === null;
        },

        showProductVariantsInfo() {
            return this.selectedProfile &&
                this.selectedProfile.sourceEntity === 'product' &&
                this.config &&
                this.config.parameters &&
                this.config.parameters.includeVariants;
        },

        logRepository() {
            return this.repositoryFactory.create('import_export_log');
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
            this.progressTotal = 0;
            this.progressText = '';
            this.progressState = '';
            this.progressLogEntry = null;
        },

        onStartProcess() {
            this.isLoading = true;
            this.resetProgressStats();

            this.importExport.export(this.selectedProfileId, this.handleProgress, this.config).then(res => {
                const logEntry = res.data.log;

                this.logRepository.get(logEntry.id).then((entry) => {
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
            this.progressOffset = progress.offset;
            this.progressTotal = progress.total;
            // ToDo snippet text for states
            this.progressText = progress.state;
            this.progressState = progress.state;

            if (progress.state === 'succeeded') {
                this.onProgressFinished(progress);
            }
        },

        onProgressFinished() {
            this.createNotificationSuccess({
                message: this.$tc('sw-import-export.exporter.messageExportSuccess', 0),
            });
            this.isLoading = false;
            this.$emit('export-finish');
        },

        setExportModalProfile(profileName) {
            this.exportModalProfile = profileName;
        },
    },
});
