const { Mixin, Component } = Shopware;
const { mapGetters } = Component.getComponentHelper();
/**
 * Mixin to handle notification when receiving cart response.
 */

Mixin.register('cart-notification', {
    computed: {
        ...mapGetters('swOrder', ['cartErrors']),
    },

    watch: {
        cartErrors: {
            handler(newValue) {
                this.handleNotification(newValue);
            },
        },
    },

    methods: {
        handleNotification(info) {
            if (!info || info.length === 0) {
                return;
            }

            Object.values(info).forEach((value) => {
                switch (value.level) {
                    case 0: {
                        this.createNotificationSuccess({
                            message: value.message,
                        });
                        break;
                    }

                    case 10: {
                        this.createNotificationWarning({
                            message: value.message,
                        });
                        break;
                    }

                    default: {
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
