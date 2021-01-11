import template from './sw-extension-file-upload.html.twig';
import './sw-extension-file-upload.scss';
import pluginErrorHandler from '../../service/extension-error-handler.service';

const { Component, Mixin, State } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-extension-file-upload', {
    template,

    inject: ['extensionApiService', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false
        };
    },

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
            this.isLoading = true;
            const formData = new FormData();
            formData.append('file', files[0]);

            const searchData = {
                repository: this.repositoryFactory.create('plugin'),
                criteria: new Criteria(),
                context: {
                    ...Shopware.Context.api,
                    languageId: Shopware.State.get('session').languageId
                }
            };

            return this.extensionApiService.upload(formData).then(() => {
                State.dispatch('shopwareExtensions/updateInstalledExtensions', searchData).then(() => {
                    return this.createNotificationSuccess({
                        message: this.$tc('sw-extension.my-extensions.fileUpload.messageUploadSuccess')
                    });
                });
            }).catch((exception) => {
                const mappedErrors = pluginErrorHandler.mapErrors(exception.response.data.errors);
                mappedErrors.forEach((error) => {
                    if (error.parameters) {
                        this.showStoreError(error);
                        return;
                    }

                    this.createNotificationError({
                        message: this.$tc(error.message)
                    });
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        showStoreError(error) {
            const docLink = this.$tc('sw-extension.errors.messageToTheShopwareDocumentation', 0, error.parameters);
            this.createNotificationError({
                message: `${error.message} ${docLink}`,
                autoClose: false
            });
        }
    }
});
