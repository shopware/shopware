import { Mixin } from 'src/core/shopware';

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
            storeCode: 'STORE-API',
            errors: {
                'NO-PLUGIN-FOUND-IN-ZIP': {
                    title: 'titleUploadFailure',
                    message: 'messageUploadFailureNoPluginFoundInZipFile'
                },
                'PLUGIN-NOT-A-ZIP-FILE': {
                    title: 'titleUploadFailure',
                    message: 'messageUploadFailureNotAZipFile'
                },
                'PLUGIN-EXTRACTION': {
                    title: 'titleUploadFailure',
                    message: 'messageUploadFailureUnzipFailed'
                },
                'INVALID-CREDENTIALS': {
                    title: 'titleLoginDataInvalid',
                    message: 'messageLoginDataInvalid'
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
                        this.createNotificationError({
                            title: error.title,
                            message: error.detail
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
