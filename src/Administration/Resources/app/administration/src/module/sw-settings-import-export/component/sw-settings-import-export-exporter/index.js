import template from './sw-settings-import-export-exporter.html.twig';
import './sw-settings-import-export-exporter.scss';

const { Mixin } = Shopware;

// TODO: Bitte die ganze Komponente Unit testen!
Shopware.Component.register('sw-settings-import-export-exporter', {
    template,

    inject: ['importExport'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            selectedProfileId: null,
            progressIndex: 0,
            totalProgress: null,
            statusText: '',
            stats: null,
            isLoading: false
        };
    },

    computed: {
        percentageExportProgress() {
            return this.progressIndex / this.totalProgress * 100;
        },

        disableExporting() {
            return this.isLoading || this.selectedProfileId === null;
        },

        progressBarClasses() {
            return {
                'sw-settings-import-export-exporter__progress-bar-bar--finished': this.percentageExportProgress >= 100
            };
        }
    },

    methods: {
        onStartExport() {
            this.isLoading = true;

            this.importExport.export(this.selectedProfileId, this.handleExportProgress).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-import-export.exporter.titleExportSuccess'),
                    message: this.$tc('sw-settings-import-export.exporter.messageExportSuccess', 0)
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        handleExportProgress(progress) {
            this.progressIndex = progress.index;
            this.totalProgress = progress.maxIndex;
            this.statusText = progress.statusText;

            if (progress.status === 'finished') {
                this.stats = progress.stats;
                this.isLoading = false;
            }
        }
    }
});
