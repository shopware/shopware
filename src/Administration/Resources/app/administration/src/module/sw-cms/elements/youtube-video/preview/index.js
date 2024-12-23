import template from './sw-cms-el-preview-youtube-video.html.twig';
import './sw-cms-el-preview-youtube-video.scss';

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
