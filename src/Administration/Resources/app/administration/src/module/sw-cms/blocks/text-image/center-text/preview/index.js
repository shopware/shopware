import template from './sw-cms-preview-center-text.html.twig';
import './sw-cms-preview-center-text.scss';

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
