import template from './sw-settings-import-export-importer.html.twig';
import './sw-settings-import-export-importer.scss';

const { Mixin } = Shopware;

// TODO: Bitte die ganze Komponente Unit testen!
// TODO: Upload der Datei bitte E2E testen, ob alles korrekt importiert wird (inkl. UI)
Shopware.Component.register('sw-settings-import-export-importer', {
    template,

    inject: ['importExport'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            selectedProfile: null,
            importFile: null,
            progressIndex: 0,
            totalProgress: null,
            statusText: '',
            stats: null,
            isLoading: false
        };
    },

    computed: {
        percentageImportProgress() {
            return this.progressIndex / this.totalProgress * 100;
        },

        disableImporting() {
            return this.isLoading || this.selectedProfile === null || this.importFile === null;
        },

        progressBarClasses() {
            return {
                'sw-settings-import-export-importer__progress-bar-bar--finished': this.percentageImportProgress >= 100
            };
        }
    },

    methods: {
        onStartImport() {
            this.isLoading = true;
            const profile = this.selectedProfile;
            const file = this.importFile;

            this.importExport.import(profile, file, this.handleImportProgress).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-import-export.importer.titleImportSuccess'),
                    message: this.$tc('sw-settings-import-export.importer.messageImportSuccess')
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        handleImportProgress(progress) {
            this.progressIndex = progress.index;
            this.totalProgress = progress.maxIndex;
            this.statusText = progress.statusText;

            if (progress.status === 'finished') {
                this.stats = progress.stats;
                this.isLoading = false;
                this.$emit('import-finish');
            }
        }
    }
});
