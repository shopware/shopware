/**
 * @package admin
 */

import type Vue from 'vue';
import { defineComponent } from 'vue';

/* @private */
export {};

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default Shopware.Mixin.register('remove-api-error', defineComponent({
    created() {
        if (typeof this.$options.$apiErrorHandler === 'function') {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            this.$options.$apiErrorHandler(this);
        }
    },

    $apiErrorHandler($vm: Vue) {
        let property = 'value';
        if ($vm.$options.model?.prop) {
            property = $vm.$options.model.prop;
        }

        $vm.$watch(
            property,
            /* eslint-disable-next-line */
            function watchEventProperty() {
                // @ts-expect-error
                if (this.$attrs.error && this.$attrs.error.selfLink) {
                    void Shopware.State.dispatch(
                        'error/removeApiError',
                        // @ts-expect-error
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                        { expression: this.$attrs.error.selfLink },
                    );
                }
            },
        );
    },
}));
