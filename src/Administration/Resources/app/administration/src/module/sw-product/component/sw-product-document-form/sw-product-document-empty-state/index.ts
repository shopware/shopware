/**
 * @sw-package inventory
 */
import template from './sw-product-document-empty-state.html.twig';
import './sw-product-document-empty-state.scss';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    computed: {
        isSystemDefaultLanguage(): boolean {
            return Shopware.Store.get('context').isSystemDefaultLanguage;
        },
    },
});
