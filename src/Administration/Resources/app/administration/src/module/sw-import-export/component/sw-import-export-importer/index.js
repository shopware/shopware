/**
 * @package system-settings
 */
import template from './sw-import-export-importer.html.twig';
import './sw-import-export-importer.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
export default {
    template,

    inject: ['importExport', 'repositoryFactory', 'feature'],

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
            isLoading: false,
            importFile: null,
            importModalProfile: null,
        };
    },

    computed: {
        profileCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('label'));

            if (this.sourceEntity.length > 0) {
                criteria.addFilter(
                    Criteria.equals('sourceEntity', this.sourceEntity),
                );
            }
            criteria.addFilter(Criteria.not('AND', [Criteria.equals('type', 'export')]));

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
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('invalidRecordsLog');
            criteria.addAssociation('file');

            return criteria;
        },
    },

    methods: {
        onProfileSelect(profileId, profile) {
            this.selectedProfileId = profileId;
            this.selectedProfile = profile;
        },

        onStartProcess(dryRun = false) {
            this.isLoading = true;

            const profile = this.selectedProfileId;

            this.importExport.import(profile, this.importFile, this.handleProgress, this.config, dryRun).then(() => {
                this.importFile = null;
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

                this.isLoading = false;
            });
        },

        onStartDryRunProcess() {
            this.onStartProcess(true);
        },

        handleProgress(log) {
            this.createNotificationInfo({
                message: this.$tc('sw-import-export.importer.messageImportStarted'),
            });

            this.isLoading = false;
            this.$emit('import-started', log);
        },

        setImportModalProfile(profileName) {
            this.importModalProfile = profileName;
        },
    },
};
