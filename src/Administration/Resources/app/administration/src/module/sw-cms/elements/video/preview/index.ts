import template from './sw-cms-el-preview-video.html.twig';
import './sw-cms-el-preview-video.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    computed: {
        assetFilter(): (value: string) => string {
            return Shopware.Filter.getByName('asset');
        },
    },
};
