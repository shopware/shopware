/**
 * @package merchant-services
 * @deprecated tag:v6.5.0 - Will be private
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
