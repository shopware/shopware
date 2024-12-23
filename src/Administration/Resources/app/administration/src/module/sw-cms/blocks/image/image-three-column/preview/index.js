import template from './sw-cms-preview-image-three-column.html.twig';
import './sw-cms-preview-image-three-column.scss';

/**
 * @private
 * @package discovery
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
