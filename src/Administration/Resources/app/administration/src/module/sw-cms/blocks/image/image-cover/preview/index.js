import template from './sw-cms-preview-image-cover.html.twig';
import './sw-cms-preview-image-cover.scss';

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
