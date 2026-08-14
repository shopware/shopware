import { defineComponent } from 'vue';

/**
 * @sw-package checkout
 * @private
 *
 * Duplicated in `src/module/sw-extension/composables/use-extension-error`; change both together.
 */
export default Shopware.Mixin.register(
    'sw-extension-error',
    defineComponent({
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
    }),
);
