import template from './sw-import-export-progress.html.twig';
import './sw-import-export-progress.scss';

const { Mixin } = Shopware;

/**
 * @private
 */
Shopware.Component.register('sw-import-export-progress', {
    template,

    inject: ['importExport'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        activityType: {
            type: String,
            required: false,
            default: 'import',
            validValues: [
                'import',
                'export',
            ],
            validator(value) {
                return [
                    'import',
                    'export',
                ].includes(value);
            },
        },

        offset: {
            type: Number,
            required: false,
            default: 0,
        },

        total: {
            type: Number,
            required: false,
            default: null,
        },

        state: {
            type: String,
            required: false,
            default: null,
        },

        disableButton: {
            type: Boolean,
            required: false,
            default: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        logEntry: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            stateText: {
                import: {
                    succeeded: this.$tc('sw-import-export.progress.succeededImportText'),
                    failed: this.$tc('sw-import-export.progress.failedImportText'),
                    progress: this.$tc('sw-import-export.progress.progressImportText'),
                },
                export: {
                    succeeded: this.$tc('sw-import-export.progress.succeededExportText'),
                    failed: this.$tc('sw-import-export.progress.failedExportText'),
                    progress: this.$tc('sw-import-export.progress.progressExportText'),
                },
            },
            selectedLog: null,
        };
    },

    computed: {
        progressBarClasses() {
            return {
                'is--finished': (this.percentageProgress >= 100) && this.state === 'succeeded',
                'is--errored': this.state === 'failed',
            };
        },

        percentageProgress() {
            if (this.total === 0) {
                return 0;
            }
            return this.offset / this.total * 100;
        },

        logEntryState() {
            if (!this.logEntry) {
                return '';
            }

            return this.stateText[this.activityType][this.logEntry.state];
        },

        successMessage() {
            let typeLabel = '';
            if (this.activityType === 'import') {
                typeLabel = this.$tc('sw-import-export.importer.importLabel');
            } else {
                typeLabel = this.$tc('sw-import-export.exporter.exportLabel');
            }

            return `${typeLabel} ${this.$tc('sw-import-export.progress.successTitle')}`;
        },

        entriesLabel() {
            if (this.activityType === 'import') {
                return this.$tc('sw-import-export.progress.fileSizeLabel');
            }

            return this.$tc('sw-import-export.progress.entriesLabel');
        },
    },

    methods: {
        getDownloadUrl(id, accessToken) {
            return this.importExport.getDownloadUrl(id, accessToken);
        },

        onShowLog(item) {
            this.selectedLog = item;
        },

        closeSelectedLog() {
            this.selectedLog = null;
        },
    },
});
