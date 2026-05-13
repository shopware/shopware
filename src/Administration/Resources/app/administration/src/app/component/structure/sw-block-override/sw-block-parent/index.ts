import { h, inject, shallowRef, watch } from 'vue';
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
        const providedParents = inject(parentsInjectionKey, null);
        const parent = shallowRef(providedParents?.value.pop());

        // The sw-block component replaces the parent stack array on each render.
        // This watcher is intentionally not deep, so consuming one entry with pop()
        // does not schedule itself again.
        watch(providedParents ?? shallowRef([]), (parents) => {
            parent.value = parents.pop();
        });

        return {
            parent,
        };
    },
    render() {
        return h(() => this.parent);
    },
});
