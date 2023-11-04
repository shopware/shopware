import template from './sw-cms-block-product-heading.html.twig';
import './sw-cms-block-product-heading.scss';

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
