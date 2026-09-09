/**
 * @sw-package framework
 */
import { computed, provide } from 'vue';
import { string } from 'shopware:utils';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template: '<slot />',
    inheritAttrs: false,
    setup(_props, { attrs }) {
        Object.keys(attrs).forEach((key) =>
            provide(
                string.camelCase(key),
                computed(() => attrs[key]),
            ),
        );
        return {};
    },
});
