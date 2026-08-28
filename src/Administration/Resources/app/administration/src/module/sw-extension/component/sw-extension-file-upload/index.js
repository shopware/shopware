import template from './sw-extension-file-upload.html.twig';
import './sw-extension-file-upload.scss';
import pluginErrorHandler from '../../service/extension-error-handler.service';

const { Mixin } = Shopware;

const USER_CONFIG_KEY = 'extension.plugin_upload';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    inject: [
        'extensionStoreActionService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: true,
            confirmModalVisible: false,
            shouldHideConfirmModal: false,
            pluginUploadUserConfig: null,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.getUserConfig();
            this.isLoading = false;
        },

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

            return this.extensionStoreActionService
                .upload(formData)
                .then(() => {
                    Shopware.Service('shopwareExtensionService')
                        .updateExtensionData()
                        .then(() => {
                            return this.createNotificationSuccess({
                                message: this.$t('sw-extension.my-extensions.fileUpload.messageUploadSuccess'),
                            });
                        });
                })
                .catch((exception) => {
                    const mappedErrors = pluginErrorHandler.mapErrors(exception.response.data.errors);
                    mappedErrors.forEach((error) => {
                        if (error.parameters) {
                            this.showStoreError(error);
                            return;
                        }

                        const message = [
                            this.$t(error.message),
                            error.details,
                        ]
                            .filter(Boolean)
                            .join('<br />');

                        this.createNotificationError({
                            message: message,
                        });
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                    this.confirmModalVisible = false;

                    if (this.shouldHideConfirmModal === true) {
                        this.saveConfig(true);
                    }
                });
        },

        showStoreError(error) {
            const docLink = this.$t('sw-extension.errors.messageToTheShopwareDocumentation', error.parameters, 0);
            this.createNotificationError({
                message: `${error.message} ${docLink}`,
                autoClose: false,
            });
        },

        showConfirmModal() {
            if (this.pluginUploadUserConfig.value.hide_upload_warning === true) {
                this.onClickUpload();
                return;
            }

            this.confirmModalVisible = true;
        },

        closeConfirmModal() {
            this.confirmModalVisible = false;
        },

        async getUserConfig() {
            this.pluginUploadUserConfig = {
                key: USER_CONFIG_KEY,
                value: (await Shopware.Service('userConfigService').search([USER_CONFIG_KEY]))?.data?.[USER_CONFIG_KEY] || {
                    hide_upload_warning: false,
                },
            };
        },

        saveConfig(value) {
            this.pluginUploadUserConfig.value = {
                hide_upload_warning: value,
            };

            Shopware.Service('userConfigService')
                .upsert({
                    [USER_CONFIG_KEY]: this.pluginUploadUserConfig.value,
                })
                .then(() => {
                    this.getUserConfig();
                });
        },
    },
};
