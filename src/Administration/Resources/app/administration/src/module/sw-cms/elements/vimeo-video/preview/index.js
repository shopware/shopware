import template from './sw-cms-el-preview-vimeo-video.html.twig';
import './sw-cms-el-preview-vimeo-video.scss';

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
