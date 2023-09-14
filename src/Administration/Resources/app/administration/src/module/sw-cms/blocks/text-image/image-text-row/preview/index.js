import template from './sw-cms-preview-image-text-row.html.twig';
import './sw-cms-preview-image-text-row.scss';

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
