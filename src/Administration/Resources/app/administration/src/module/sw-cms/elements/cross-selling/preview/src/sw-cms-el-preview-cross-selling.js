import template from './sw-cms-el-preview-cross-selling.html.twig';
import './sw-cms-el-preview-cross-selling.scss';
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
