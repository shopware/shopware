/**
 * @package merchant-services
 * @private
 */
Shopware.Mixin.register('sw-extension-error', {
    mixins: [Shopware.Mixin.getByName('notification')],

    methods: {
        showExtensionErrors(errorResponse) {
            Shopware.Service('extensionErrorService')
                .handleErrorResponse(errorResponse, this)
                .forEach((notification) => {
                    this.createNotificationError(notification);
                });
        },
    },
});
