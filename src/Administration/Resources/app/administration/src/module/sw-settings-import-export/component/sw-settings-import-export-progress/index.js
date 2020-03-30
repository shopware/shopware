import template from './sw-settings-import-export-progress.html.twig';
import './sw-settings-import-export-progress.scss';

const { Mixin } = Shopware;

/**
 * @private
 */
Shopware.Component.register('sw-settings-import-export-progress', {
    template,

    inject: ['importExport'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        activityType: {
            type: String,
            required: false,
            default: 'import',
            validValues: [
                'import',
                'export'
            ],
            validator(value) {
                return [
                    'import',
                    'export'
                ].includes(value);
            }
        },

        offset: {
            type: Number,
            required: false,
            default: 0
        },

        total: {
            type: Number,
            required: false,
            default: 1
        },

        state: {
            type: String,
            required: false,
            default: null
        },

        disableButton: {
            type: Boolean,
            required: false,
            default: true
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false
        },

        logEntry: {
            type: Object,
            required: false,
            default: null
        }
    },

    data() {
        return {
            stateText: {
                succeeded: this.$tc('sw-settings-import-export.progress.succeededText'),
                failed: this.$tc('sw-settings-import-export.progress.failedText'),
                progress: this.$tc('sw-settings-import-export.progress.progressText')
            }
        };
    },

    computed: {
        progressBarClasses() {
            return {
                'sw-settings-import-export-importer__progress-bar-bar--finished': this.percentageProgress >= 100
            };
        },

        percentageProgress() {
            return this.offset / this.total * 100;
        },

        logEntryState() {
            if (!this.logEntry) {
                return '';
            }

            return this.stateText[this.logEntry.state];
        },

        successMessage() {
            let typeLabel = '';
            if (this.activityType === 'import') {
                typeLabel = this.$tc('sw-settings-import-export.importer.importLabel');
            } else {
                typeLabel = this.$tc('sw-settings-import-export.exporter.exportLabel');
            }

            return `${typeLabel} ${this.$tc('sw-settings-import-export.progress.successTitle')}`;
        },

        entriesLabel() {
            if (this.activityType === 'import') {
                return this.$tc('sw-settings-import-export.progress.fileSizeLabel');
            }

            return this.$tc('sw-settings-import-export.progress.entriesLabel');
        }
    },

    methods: {
        getDownloadUrl(id, accessToken) {
            return this.importExport.getDownloadUrl(id, accessToken);
        }
    }
});
