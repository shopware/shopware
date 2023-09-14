import template from './sw-cms-preview-cross-selling.html.twig';
import './sw-cms-preview-cross-selling.scss';

/**
 * @private
 * @package content
 */
export default {
    template,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
