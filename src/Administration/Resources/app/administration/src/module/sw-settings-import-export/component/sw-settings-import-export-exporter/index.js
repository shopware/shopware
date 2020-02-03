import template from './sw-settings-import-export-exporter.html.twig';
import './sw-settings-import-export-exporter.scss';

// TODO: Bitte die ganze Komponente Unit testen!
Shopware.Component.register('sw-settings-import-export-exporter', {
    template,

    inject: ['importExport'],

    data() {
        return {
            selectedProfile: null,
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
            return this.isLoading || this.selectedProfile === null;
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
            // TODO: Replace mock with real profile (this.selectedProfile)
            const profile = {};

            this.importExport.export(profile, this.handleExportProgress);
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
