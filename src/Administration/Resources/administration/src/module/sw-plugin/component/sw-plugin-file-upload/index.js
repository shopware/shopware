import { Component, Mixin } from 'src/core/shopware';
import template from './sw-plugin-file-upload.html.twig';
import './sw-plugin-file-upload.scss';

Component.register('sw-plugin-file-upload', {
    template,

    inject: ['pluginService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('plugin-error-handler')
    ],

    methods: {
        onClickUpload() {
            this.$refs.fileInput.click();
        },

        onFileInputChange() {
            const newFiles = Array.from(this.$refs.fileInput.files);
            this.handleUpload(newFiles);
            this.$refs.fileForm.reset();
        },

        handleUpload(files) {
            const formData = new FormData();
            formData.append('file', files[0]);

            this.pluginService.upload(formData).then(() => {
                this.$emit('sw-plugin-file-upload-success');
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.fileUpload.titleUploadSuccess'),
                    message: this.$tc('sw-plugin.fileUpload.messageUploadSuccess')
                });
            }).catch((exception) => {
                this.handleErrorResponse(exception);
            });
        }
    }
});
