import type { CartError } from '../order.types';

/**
 * @package customer-order
 */

const { State, Mixin } = Shopware;
/**
 * Mixin to handle notification when receiving cart response.
 */
Mixin.register('cart-notification', {
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
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                        this.createNotificationSuccess({
                            message: value.message,
                        });
                        break;
                    }

                    case 10: {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                        this.createNotificationWarning({
                            message: value.message,
                        });
                        break;
                    }

                    default: {
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
});
