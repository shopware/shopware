import template from './sw-cms-el-preview-video.html.twig';
import './sw-cms-el-preview-video.scss';
import { getAssetFilter } from '../dependencies';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    computed: {
        assetFilter(): (value: string) => string {
            return getAssetFilter();
        },
    },
};
