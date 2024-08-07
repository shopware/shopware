import template from './sw-cms-preview-image-four-column.html.twig';
import './sw-cms-preview-image-four-column.scss';

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
