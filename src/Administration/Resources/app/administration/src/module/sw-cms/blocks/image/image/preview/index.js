import template from './sw-cms-preview-image.html.twig';
import './sw-cms-preview-image.scss';

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
