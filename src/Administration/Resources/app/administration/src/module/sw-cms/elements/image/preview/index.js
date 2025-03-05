import template from './sw-cms-el-preview-image.html.twig';
import './sw-cms-el-preview-image.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
