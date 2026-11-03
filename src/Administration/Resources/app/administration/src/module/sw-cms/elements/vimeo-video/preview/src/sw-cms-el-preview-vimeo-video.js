import template from './sw-cms-el-preview-vimeo-video.html.twig';
import './sw-cms-el-preview-vimeo-video.scss';
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
