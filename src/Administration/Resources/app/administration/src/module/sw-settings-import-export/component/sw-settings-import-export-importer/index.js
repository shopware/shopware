import template from './sw-settings-import-export-importer.html.twig';
import './sw-settings-import-export-importer.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
Shopware.Component.register('sw-settings-import-export-importer', {
    template,

    inject: ['importExport', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            selectedProfile: null,
            progressOffset: 0,
            progressTotal: null,
            progressText: '',
            progressState: '',
            progressLogEntry: null,
            isLoading: false,
            importFile: null
        };
    },

    computed: {
        logRepository() {
            return this.repositoryFactory.create('import_export_log');
        },

        disableImporting() {
            return this.isLoading || this.selectedProfile === null || this.importFile === null;
        },

        logCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('invalidRecordsLog');
            criteria.addAssociation('file');

            return criteria;
        }
    },

    methods: {
        onStartProcess() {
            this.isLoading = true;

            // Reset progress stats
            this.progressOffset = 0;
            this.progressTotal = null;
            this.progressText = '';
            this.progressState = '';
            this.progressLogEntry = null;

            const profile = this.selectedProfile;

            this.importExport.import(profile, this.importFile, this.handleProgress).then((result) => {
                const logEntry = result.data.log;

                this.logRepository.get(logEntry.id, Shopware.Context.api, this.logCriteria).then((entry) => {
                    this.progressLogEntry = entry;
                });
            });
        },

        handleProgress(progress) {
            this.progressOffset = Math.round(progress.offset / 1024); // Convert byte to kilobyte
            this.progressTotal = Math.round(progress.total / 1024); // Convert byte to kilobyte
            this.progressState = progress.state;

            if (progress.state === 'succeeded') {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-import-export.importer.titleImportSuccess'),
                    message: this.$tc('sw-settings-import-export.importer.messageImportSuccess', 0)
                });
                this.onProgressFinished();
            } else if (progress.state === 'failed') {
                this.createNotificationError({
                    title: this.$tc('sw-settings-import-export.importer.titleImportError'),
                    message: this.$tc('sw-settings-import-export.importer.messageImportError', 0)
                });
                this.onProgressFinished();
            }
        },

        onProgressFinished() {
            this.isLoading = false;
            this.$emit('import-finish');
        }
    }
});
