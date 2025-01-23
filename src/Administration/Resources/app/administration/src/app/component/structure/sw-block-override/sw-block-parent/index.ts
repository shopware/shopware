import { h, inject } from 'vue';
import parentsInjectionKey from '../sw-block/parents-injection-key';

/**
 * @sw-package framework
 *
 * @description
 * The `sw-block-parent` component is used to render the parent block content. It is used in combination with the
 * `sw-block` component to extend the content of the `sw-block-extension` component.
 * See the `sw-block-extension` component for more information.
 *
 * @private
 *
 */
Shopware.Component.register('sw-block-parent', {
    compatConfig: Shopware.compatConfig,
    setup() {
        const parent = inject(parentsInjectionKey, null)?.value.pop();

        return {
            parent,
        };
    },
    render() {
        return h(() => this.parent);
    },
});
