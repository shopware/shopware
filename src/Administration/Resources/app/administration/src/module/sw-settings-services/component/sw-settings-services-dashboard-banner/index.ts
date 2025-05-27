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

    created() {
        Shopware.Service('userConfigService')
            .search(['core.show-services-dashboard-banner'])
            .then((response) => {
                if (typeof response === 'undefined') {
                    this.isHidden = false;
                    return;
                }

                this.isHidden = (response.data['core.show-services-dashboard-banner']?.[0] as boolean|undefined) ?? false;
            }).catch(() => { this.isHidden = false; });
    },

    methods: {
        async hideBanner() {
            await Shopware.Service('userConfigService')
                .upsert({
                    'core.show-services-dashboard-banner': [true],
                });

            this.isHidden = true;
        },
    },
})