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
            return this.state === 'aborted';
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
                default:
                    return 'info';
            }
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
            if (this.log.records > 0) {
                this.process(0);
            } else {
                this.complete();
            }
        },

        process(offset) {
            this.percentage = offset / this.log.records * 100;
            this.state = 'progress';
            this.importExportService.process(this.log.id, offset).then((response) => {
                if (this.aborted || this.failed) {
                    return;
                }

                offset += response.processed;
                if (offset < this.log.records) {
                    this.process(offset);
                    return;
                }

                this.complete();
            }).catch(() => {
                this.fail();
            });
        },

        complete() {
            this.percentage = 100;
            this.state = 'succeeded';
            if (this.log.activity === 'export') {
                this.downloadUrl = this.importExportService.getDownloadUrl(this.log.file.id, this.log.file.accessToken);
            }
        },

        abort() {
            this.state = 'aborted';
        },

        fail() {
            this.state = 'failed';
        },

        closeModal() {
            if (this.progress) {
                return;
            }
            this.$emit('modal-close');
        },

        onUserCancel() {
            this.abort();
            this.importExportService.cancel(this.log.id);
        }
    }
});
