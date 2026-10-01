import { computed, inject } from 'vue';
import parentsInjectionKey from '../sw-block/parents-injection-key';
import reduceToSingleRoot from '../reduce-to-single-root';

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
        const initialParents = parents?.value;
        const initialParent = initialParents?.pop();
        const parentIndex = initialParents ? initialParents.length : -1;
        // Reserve the stack slot once, then read the current VNode at that slot after reactive parent updates.
        const parent = computed(() => {
            if (parentIndex < 0 || !parents || parents.value === initialParents) {
                return initialParent;
            }

            return parents.value[parentIndex];
        });

        return {
            parent,
        };
    },
    // The parent content is returned directly instead of through a wrapping functional component:
    // a fresh arrow function as the VNode type on every render reads to Vue as a different
    // component and makes it unmount plus remount the content, and a functional component would
    // additionally swallow every fallthrough attribute except class, style and listeners.
    render() {
        return reduceToSingleRoot(this.parent);
    },
});
