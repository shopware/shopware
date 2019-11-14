import template from './sw-plugin-file-upload.html.twig';
import './sw-plugin-file-upload.scss';

const { Component, Mixin } = Shopware;

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
                this.$emit('upload-success');
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
