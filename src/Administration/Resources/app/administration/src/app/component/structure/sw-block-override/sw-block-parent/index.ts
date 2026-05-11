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
export default Shopware.Component.wrapComponentConfig({
    setup() {
        const parents = inject(parentsInjectionKey, null);
        const parent = parents?.value.pop();
        const parentIndex = parents?.value.length ?? -1;

        return {
            parent: () => parents?.value[parentIndex] ?? parent,
        };
    },
    render() {
        return h(() => this.parent());
    },
});
