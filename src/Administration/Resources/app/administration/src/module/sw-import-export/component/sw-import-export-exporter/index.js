/**
 * @package system-settings
 */
import template from './sw-import-export-exporter.html.twig';
import './sw-import-export-exporter.scss';

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
            config: {
                parameters: {},
            },
            isLoading: false,
            exportModalProfile: null,
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
            criteria.addFilter(Criteria.not('AND', [Criteria.equals('type', 'import')]));

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

        onStartProcess() {
            this.isLoading = true;

            this.importExport.export(this.selectedProfileId, this.handleProgress, this.config).catch((error) => {
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

        handleProgress(log) {
            this.createNotificationInfo({
                message: this.$tc('sw-import-export.exporter.messageExportStarted'),
            });

            this.isLoading = false;
            this.$emit('export-started', log);
        },

        setExportModalProfile(profileName) {
            this.exportModalProfile = profileName;
        },
    },
};
