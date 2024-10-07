/**
 * @package admin
 */

import { defineComponent } from 'vue';

/* @private */
export {};

/**
 * @private
 */
export default Shopware.Mixin.register(
    'remove-api-error',
    defineComponent({
        created() {
            if (typeof this.$options.$apiErrorHandler === 'function') {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.$options.$apiErrorHandler(this);
            }
        },

        $apiErrorHandler($vm: typeof this) {
            let property = 'value';
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if ($vm.$options.model?.prop) {
                // eslint-disable-next-line max-len
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
                property = $vm.$options.model.prop;
            }

            $vm.$watch(
                property,
                /* eslint-disable-next-line */
                function watchEventProperty() {
                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    if (this.$attrs.error && this.$attrs.error.selfLink) {
                        void Shopware.State.dispatch(
                            'error/removeApiError',
                            // @ts-expect-error
                            // eslint-disable-next-line max-len
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
                            { expression: this.$attrs.error.selfLink },
                        );
                    }
                },
            );
        },
    }),
);
