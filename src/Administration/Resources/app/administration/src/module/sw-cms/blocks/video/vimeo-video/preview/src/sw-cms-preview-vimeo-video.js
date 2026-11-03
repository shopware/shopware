import template from './sw-cms-preview-vimeo-video.html.twig';
import './sw-cms-preview-vimeo-video.scss';
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
