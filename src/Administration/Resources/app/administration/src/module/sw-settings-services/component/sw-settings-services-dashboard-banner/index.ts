/**
 * @sw-package framework
 */
import template from './sw-settings-services-dashboard-banner.html.twig';
import './sw-settings-services-dashboard-banner.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-dashboard-banner',

    template,

    data() {
        const assetFilter = Shopware.Filter.getByName('asset');

        return {
            isHidden: true,
            // eslint-disable-next-line max-len
            servicesGraphicLight: assetFilter(
                '/administration/administration/static/img/services/services-graphic-light.svg',
            ),
            servicesGraphicDark: assetFilter('/administration/administration/static/img/services/services-graphic-dark.svg'),
        };
    },

    created() {
        Shopware.Service('userConfigService')
            .search(['core.hide-services-dashboard-banner'])
            .then((response) => {
                if (typeof response === 'undefined') {
                    this.isHidden = false;
                    return;
                }

                if (!response.data) {
                    this.isHidden = false;
                    return;
                }

                this.isHidden = (response.data['core.hide-services-dashboard-banner']?.[0] as boolean | undefined) ?? false;
            })
            .catch(() => {
                this.isHidden = false;
            });
    },

    methods: {
        async hideBanner() {
            await Shopware.Service('userConfigService').upsert({
                'core.hide-services-dashboard-banner': [true],
            });

            this.isHidden = true;
        },
    },
});
