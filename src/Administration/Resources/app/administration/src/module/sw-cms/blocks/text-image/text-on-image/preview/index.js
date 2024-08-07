import template from './sw-cms-preview-text-on-image.html.twig';
import './sw-cms-preview-text-on-image.scss';

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
