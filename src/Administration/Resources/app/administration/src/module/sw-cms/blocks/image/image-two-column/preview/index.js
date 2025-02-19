import template from './sw-cms-preview-image-two-column.html.twig';
import './sw-cms-preview-image-two-column.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
