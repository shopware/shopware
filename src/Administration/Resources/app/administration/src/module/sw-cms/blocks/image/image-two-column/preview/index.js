import template from './sw-cms-preview-image-two-column.html.twig';
import './sw-cms-preview-image-two-column.scss';

/**
 * @private
 * @package buyers-experience
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
