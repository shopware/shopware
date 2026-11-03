import template from './sw-cms-el-preview-image-slider.html.twig';
import './sw-cms-el-preview-image-slider.scss';
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
