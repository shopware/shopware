/**
 * @sw-package framework
 */
import template from './sw-settings-services-dashboard-banner.html.twig';
import './sw-settings-services-dashboard-banner.scss';
import servicesGraphics from './assets/services-graphic.png'

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-usage-data-consent-banner',

    template,

    data() {
        return {
            isHidden: false,
            servicesGraphics,
        };
    },
})