import template from './sw-cms-preview-image-text-bubble.html.twig';
import './sw-cms-preview-image-text-bubble.scss';

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
