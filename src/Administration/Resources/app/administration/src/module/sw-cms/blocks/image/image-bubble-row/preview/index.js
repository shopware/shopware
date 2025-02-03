import template from './sw-cms-preview-image-bubble-row.html.twig';
import './sw-cms-preview-image-bubble-row.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
