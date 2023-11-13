import template from './sw-cms-preview-gallery-buybox.html.twig';
import './sw-cms-preview-gallery-buybox.scss';

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
