/** @sw-package framework */
import { inject } from 'vue';
import parentsInjectionKey from '../sw-block/parents-injection-key';
import reduceToSingleRoot from '../reduce-to-single-root';

/**
 * Render the immutable predecessor of the current override layer. Each occurrence gets fresh VNodes,
 * so sibling parent calls do not consume each other's content.
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    setup() {
        const renderParent = inject(parentsInjectionKey, () => []);
        return { renderParent };
    },
    render() {
        return reduceToSingleRoot(this.renderParent());
    },
});
