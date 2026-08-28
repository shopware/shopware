import { defineComponent } from 'vue';
import type { CartError } from '../order.types';
import { getTranslatedCartErrorMessage } from '../cart-error.helper';

/**
 * @sw-package checkout
 */

const { Store, Mixin } = Shopware;
/**
 * @private
 *
 * Mixin to handle notification when receiving cart response.
 */
export default Mixin.register(
    'cart-notification',
    defineComponent({
        computed: {
            cartErrors(): CartError[] {
                return Store.get('swOrder').cartErrors;
            },
        },

        watch: {
            cartErrors: {
                handler(newValue: CartError[]) {
                    this.handleNotification(newValue);
                },
            },
        },

        methods: {
            handleNotification(info: CartError[]) {
                if (!info || info.length === 0) {
                    return;
                }

                Object.values(info).forEach((value) => {
                    const message = getTranslatedCartErrorMessage(value, (key, values) => this.$t(key, values ?? {}));

                    switch (value.level) {
                        case 0: {
                            // @ts-expect-error
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                            this.createNotificationSuccess({
                                message,
                            });
                            break;
                        }

                        case 10: {
                            // @ts-expect-error
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                            this.createNotificationWarning({
                                message,
                            });
                            break;
                        }

                        default: {
                            // @ts-expect-error
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                            this.createNotificationError({
                                message,
                            });
                            break;
                        }
                    }
                });
            },
        },
    }),
);
