/**
 * @sw-package framework
 */
import { defineComponent, useErrorStore } from '../dependencies';

/* @private */

/**
 * @public
 */
export default defineComponent({
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
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
            property = $vm.$options.model.prop;
        }

        $vm.$watch(property, function watchEventProperty() {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (this.$attrs.error && this.$attrs.error.selfLink) {
                void useErrorStore().removeApiError(
                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument,@typescript-eslint/no-unsafe-member-access
                    this.$attrs.error.selfLink,
                );
            }
        });
    },
});
