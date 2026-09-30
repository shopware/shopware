/**
 * @sw-package framework
 */
import { computed, provide } from 'vue';
import { camelCase } from 'shopware:utils/string';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template: '<slot />',
    inheritAttrs: false,
    setup(_props, { attrs }) {
        Object.keys(attrs).forEach((key) =>
            provide(
                camelCase(key),
                computed(() => attrs[key]),
            ),
        );
        return {};
    },
});
