import template from './sw-cms-preview-image-slider.html.twig';
import './sw-cms-preview-image-slider.scss';

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
