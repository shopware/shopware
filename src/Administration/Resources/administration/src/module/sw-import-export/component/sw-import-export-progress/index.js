import { Component } from 'src/core/shopware';
import template from './sw-import-export-progress.html.twig';

Component.register('sw-import-export-progress', {
    template,

    inject: ['importExportService'],

    props: {
        log: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            percentage: 0,
            processing: false,
            cancelled: false,
            downloadUrl: null
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.process(this.log.id, 0, this.log.records);
        },

        process(logId, offset, total) {
            if (total <= 0) {
                this.complete();
                return;
            }

            this.percentage = offset / total * 100;
            this.processing = true;
            this.importExportService.process(logId, offset).then((response) => {
                offset += response.processed;

                if (!this.cancelled && response.processed > 0) {
                    this.process(logId, offset, total);
                    return;
                }

                this.complete();
            }).catch(() => {
                this.processing = false;
                this.onCancel();
            });
        },

        complete() {
            this.percentage = 100;
            this.processing = false;
            if (this.log.activity === 'export') {
                this.downloadUrl = this.importExportService.getDownloadUrl(this.log.file.id, this.log.file.accessToken);
            }
        },

        closeModal() {
            if (this.processing) {
                return;
            }
            this.$emit('modal-close');
        },

        onCancel() {
            this.cancelled = true;
            this.importExportService.cancel(this.log.id);
        }
    }
});
