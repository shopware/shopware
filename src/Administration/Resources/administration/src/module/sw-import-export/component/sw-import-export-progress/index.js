import template from './sw-import-export-progress.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-import-export-progress', {
    template,

    inject: ['importExportService'],

    mixins: [
        Mixin.getByName('notification')
    ],

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
                this.state = 'progress';
                this.createNotificationInfo({
                    title: this.notificationTitle(),
                    message: this.notificationMessage()
                });
                this.process(0);
            } else {
                this.complete();
            }
        },

        process(offset) {
            this.percentage = offset / this.log.records * 100;
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
            this.createNotificationSuccess({
                title: this.notificationTitle(),
                message: this.notificationMessage(),
                autoClose: false
            });
            if (this.log.activity === 'export') {
                this.downloadUrl = this.importExportService.getDownloadUrl(this.log.file.id, this.log.file.accessToken);
            }
        },

        abort() {
            this.state = 'aborted';
            this.createNotificationWarning({
                title: this.notificationTitle(),
                message: this.notificationMessage(),
                autoClose: false
            });
        },

        fail() {
            this.state = 'failed';
            this.createNotificationError({
                title: this.notificationTitle(),
                message: this.notificationMessage(),
                autoClose: false
            });
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
        },

        notificationTitle() {
            return this.$tc(`sw-import-export-progress.notificationTitle.${this.log.activity}`);
        },

        notificationMessage() {
            return this.$tc(`sw-import-export-progress.messages.${this.state}`);
        }
    }
});
