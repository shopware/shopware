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

    computed: {
        aborted() {
            return this.state === 'aborted'
        },
        failed() {
            return this.state === 'failed';
        },
        progress() {
            return this.state === 'progress';
        },
        succeeded() {
            return this.state === 'succeeded';
        },
        alertVariant() {
            switch (true) {
                case this.aborted:
                    return 'warning';
                case this.failed:
                    return 'error';
                case this.succeeded:
                    return 'success';
            }
            return 'info';
        },
        alertMessage() {
            return this.$tc(`sw-import-export-progress.messages.${this.state}`);
        }
    },

    data() {
        return {
            state: null,
            percentage: 0,
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
            this.state = 'progress';
            this.importExportService.process(logId, offset).then((response) => {
                if (this.aborted || this.failed) {
                    return;
                }

                offset += response.processed;
                if (offset < this.log.records) {
                    this.process(logId, offset, total);
                    return;
                }

                this.complete();
            }).catch(() => {
                this.state = 'failed';
            });
        },

        complete() {
            this.percentage = 100;
            this.state = 'succeeded';
            if (this.log.activity === 'export') {
                this.downloadUrl = this.importExportService.getDownloadUrl(this.log.file.id, this.log.file.accessToken);
            }
        },

        closeModal() {
            if (this.progress) {
                return;
            }
            this.$emit('modal-close');
        },

        onUserCancel() {
            this.state = 'aborted';
            this.importExportService.cancel(this.log.id);
        }
    }
});
