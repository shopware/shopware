import template from './sw-cms-block-gallery-buybox.html.twig';
import './sw-cms-block-gallery-buybox.scss';
import useCmsPageStore from 'shopware:stores/cmsPage';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    computed: {
        currentDeviceView() {
            return useCmsPageStore().currentCmsDeviceView;
        },

        currentDeviceViewClass() {
            return `is--${this.currentDeviceView}`;
        },
    },
};
