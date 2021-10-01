import template from './sw-import-export-exporter.html.twig';
import './sw-import-export-exporter.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
Shopware.Component.register('sw-import-export-exporter', {
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
            const criteria = new Criteria();

            if (this.sourceEntity.length > 0) {
                criteria.addFilter(
                    Criteria.equals('sourceEntity', this.sourceEntity),
                );
            }
            if (this.feature.isActive('FEATURE_NEXT_8097')) {
                criteria.addFilter(Criteria.not('AND', [Criteria.equals('type', 'import')]));
            }
            if (!this.feature.isActive('FEATURE_NEXT_16119')) {
                criteria.addFilter(Criteria.not('AND', [
                    Criteria.equals('name', 'Default orders'),
                    Criteria.equals('systemDefault', 1),
                ]));
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
            this.createNotificationSuccess({
                message: this.$tc('sw-import-export.exporter.messageExportStarted', 0),
            });

            this.isLoading = false;
            this.$emit('export-started', log);
        },

        setExportModalProfile(profileName) {
            this.exportModalProfile = profileName;
        },
    },
});
