import template from './sw-cms-el-preview-image.html.twig';
import './sw-cms-el-preview-image.scss';
import { getAssetFilter } from '../dependencies';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    computed: {
        assetFilter() {
            return getAssetFilter();
        },
    },
};
