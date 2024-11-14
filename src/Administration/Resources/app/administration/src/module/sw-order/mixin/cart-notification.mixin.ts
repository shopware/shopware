import { defineComponent } from 'vue';
import type { CartError } from '../order.types';

/**
 * @package checkout
 */

const { State, Mixin } = Shopware;
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
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                return State.getters['swOrder/cartErrors'] as CartError[];
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
                    switch (value.level) {
                        case 0: {
                            // @ts-expect-error
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                            this.createNotificationSuccess({
                                message: value.message,
                            });
                            break;
                        }

                        case 10: {
                            // @ts-expect-error
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                            this.createNotificationWarning({
                                message: value.message,
                            });
                            break;
                        }

                        default: {
                            // @ts-expect-error
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                            this.createNotificationError({
                                message: value.message,
                            });
                            break;
                        }
                    }
                });
            },
        },
    }),
);
