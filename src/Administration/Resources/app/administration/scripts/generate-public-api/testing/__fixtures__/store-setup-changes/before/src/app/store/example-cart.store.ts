/**
 * @sw-package framework
 */
import useExampleCart from '../composables/use-example-cart';

const exampleCartStore = Shopware.Store.register('exampleCart', useExampleCart);

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default exampleCartStore;
