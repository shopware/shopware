import { defineComponent } from 'vue';
import notificationMixin from 'shopware:mixins/notification';

/**
 * @sw-package checkout
 * @private
 */
export default Shopware.Mixin.register(
    'sw-extension-error',
    defineComponent({
        mixins: [notificationMixin],

        methods: {
            showExtensionErrors(errorResponse) {
                Shopware.Service('extensionErrorService')
                    .handleErrorResponse(errorResponse, this)
                    .forEach((notification) => {
                        this.createNotificationError(notification);
                    });
            },
        },
    }),
);
