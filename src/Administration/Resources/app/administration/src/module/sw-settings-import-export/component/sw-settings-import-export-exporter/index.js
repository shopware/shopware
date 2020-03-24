import template from './sw-settings-import-export-exporter.html.twig';
import './sw-settings-import-export-exporter.scss';

const { Mixin } = Shopware;

// TODO: Bitte die ganze Komponente Unit testen!
Shopware.Component.register('sw-settings-import-export-exporter', {
    template,

    inject: ['importExport', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            selectedProfileId: null,
            progressOffset: 0,
            progressTotal: null,
            progressText: '',
            progressState: '',
            progressLogEntry: null,
            isLoading: false
        };
    },

    computed: {
        disableExporting() {
            return this.isLoading || this.selectedProfileId === null;
        },
        logRepository() {
            return this.repositoryFactory.create('import_export_log');
        }
    },

    methods: {
        onStartProcess() {
            this.isLoading = true;

            this.importExport.export(this.selectedProfileId, this.handleProgress).then(res => {
                const logEntry = res.data.log;

                this.logRepository.get(logEntry.id, Shopware.Context.api).then((entry) => {
                    this.progressLogEntry = entry;
                });
            });
        },

        // Todo implement and use handleprogress
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
                title: this.$tc('sw-settings-import-export.exporter.titleExportSuccess'),
                message: this.$tc('sw-settings-import-export.exporter.messageExportSuccess', 0)
            });
            window.setTimeout(() => {
                this.isLoading = false;
                this.$emit('export-finish');
            }, 1000);
        }
    }
});
