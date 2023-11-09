import template from './sw-cms-preview-image-gallery.html.twig';
import './sw-cms-preview-image-gallery.scss';

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
