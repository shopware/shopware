import template from './sw-settings-services-dashboard-banner.html.twig';
import './sw-settings-services-dashboard-banner.scss';

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-dashboard-banner',

    template,

    data() {
        const assetFilter = Shopware.Filter.getByName('asset');

        return {
            isHidden: true,
            servicesGraphicLight: assetFilter(
                '/administration/administration/static/img/services/services-graphic-light.svg',
            ),
            servicesGraphicDark: assetFilter('/administration/administration/static/img/services/services-graphic-dark.svg'),
        };
    },

    created() {
        Shopware.Store.get('adminUserConfig')
            .get<boolean[]>('core.hide-services-dashboard-banner')
            .then((config) => {
                this.isHidden = config?.[0] ?? false;
            })
            .catch(() => {
                this.isHidden = false;
            });
    },

    methods: {
        async hideBanner() {
            await Shopware.Store.get('adminUserConfig').upsert({
                'core.hide-services-dashboard-banner': [true],
            });

            this.isHidden = true;
        },
    },
});
