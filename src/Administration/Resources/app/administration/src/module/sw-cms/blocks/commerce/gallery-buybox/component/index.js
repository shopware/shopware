import template from './sw-cms-block-gallery-buybox.html.twig';
import './sw-cms-block-gallery-buybox.scss';

const { State } = Shopware;

/**
 * @private
 * @package content
 */
export default {
    template,

    computed: {
        currentDeviceView() {
            return State.get('cmsPageState').currentCmsDeviceView;
        },

        currentDeviceViewClass() {
            if (this.currentDeviceView) {
                return `is--${this.currentDeviceView}`;
            }

            return null;
        },
    },
};
