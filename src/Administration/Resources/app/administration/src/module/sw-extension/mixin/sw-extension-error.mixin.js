import { defineComponent } from 'vue';

/**
 * @package checkout
 * @private
 */
export default Shopware.Mixin.register('sw-extension-error', defineComponent({
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
}));
