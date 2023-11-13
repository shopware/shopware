import template from './sw-cms-el-preview-image-slider.html.twig';
import './sw-cms-el-preview-image-slider.scss';

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
