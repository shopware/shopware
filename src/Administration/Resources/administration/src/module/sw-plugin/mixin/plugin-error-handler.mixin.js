const { Mixin } = Shopware;

/**
 * Mixin to handle errors from the api
 */
Mixin.register('plugin-error-handler', {

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            prefix: 'sw-plugin.errors.',
            storeCode: 'FRAMEWORK__STORE_ERROR',
            errors: {
                FRAMEWORK__PLUGIN_NO_PLUGIN_FOUND_IN_ZIP: {
                    title: 'titleUploadFailure',
                    message: 'messageUploadFailureNoPluginFoundInZipFile'
                },
                FRAMEWORK__PLUGIN_NOT_A_ZIP_FILE: {
                    title: 'titleUploadFailure',
                    message: 'messageUploadFailureNotAZipFile'
                },
                FRAMEWORK__PLUGIN_EXTRACTION_FAILED: {
                    title: 'titleUploadFailure',
                    message: 'messageUploadFailureUnzipFailed'
                },
                FRAMEWORK__STORE_INVALID_CREDENTIALS: {
                    title: 'titleLoginDataInvalid',
                    message: 'messageLoginDataInvalid'
                },
                FRAMEWORK__STORE_LICENSE_DOMAIN_IS_MISSING: {
                    title: 'titleStoreHostMissing',
                    message: 'messageStoreLicenseDomainMissing'
                }
            }
        };
    },

    methods: {
        handleErrorResponse(exception) {
            if (exception.response && exception.response.data && exception.response.data.errors) {
                const errors = exception.response.data.errors;
                errors.forEach((error) => {
                    if (error.code === this.storeCode) {
                        let message = error.detail;
                        if (error.meta && error.meta.documentationLink) {
                            message += ` (<a target="_blank" href="${error.meta.documentationLink}">${
                                this.$tc('sw-plugin.errors.messageToTheShopwareDocumentation')}</a>)`;
                        }
                        this.createNotificationError({
                            title: error.title,
                            message: message
                        });
                        return;
                    }

                    const notification = this.errors[error.code];
                    if (!notification) {
                        this.createNotificationError({
                            title: this.$tc(`${this.prefix}titleGenericFailure`),
                            message: this.$tc(`${this.prefix}messageGenericFailure`)
                        });
                        return;
                    }
                    this.createNotificationError({
                        title: this.$tc(this.prefix + notification.title),
                        message: this.$tc(this.prefix + notification.message)
                    });
                });
            }
        }
    }
});
