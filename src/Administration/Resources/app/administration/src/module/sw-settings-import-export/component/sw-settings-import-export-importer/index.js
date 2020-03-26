import template from './sw-settings-import-export-importer.html.twig';
import './sw-settings-import-export-importer.scss';

const { Mixin } = Shopware;

Shopware.Component.register('sw-settings-import-export-importer', {
    template,

    inject: ['importExport'],

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
        disableImporting() {
            return this.isLoading || this.selectedProfile === null || this.importFile === null;
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

                this.logRepository.get(logEntry.id, Shopware.Context.api).then((entry) => {
                    this.progressLogEntry = entry;
                });
            });
        },

        handleProgress(progress) {
            this.progressOffset = Math.round(progress.offset / 1024); // Convert byte to kilobyte
            this.progressTotal = Math.round(progress.total / 1024); // Convert byte to kilobyte
            // ToDo snippet text for states
            this.progressText = progress.state;
            this.progressState = progress.state;

            if (progress.state === 'succeeded') {
                this.onProgressFinished(progress);
            }
        },

        onProgressFinished() {
            this.createNotificationSuccess({
                title: this.$tc('sw-settings-import-export.importer.titleImportSuccess'),
                message: this.$tc('sw-settings-import-export.importer.messageImportSuccess', 0)
            });
            window.setTimeout(() => {
                this.isLoading = false;
                this.$emit('import-finish');
            }, 1000);
        }
    }
});
