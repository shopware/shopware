import template from './sw-cms-preview-image-three-cover.html.twig';
import './sw-cms-preview-image-three-cover.scss';

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
