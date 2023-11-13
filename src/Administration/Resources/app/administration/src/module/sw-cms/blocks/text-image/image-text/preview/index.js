import template from './sw-cms-preview-image-text.html.twig';
import './sw-cms-preview-image-text.scss';

/**
 * @private
 * @package buyers-experience
 */
export default {
    template,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
