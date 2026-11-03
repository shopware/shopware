import template from './sw-settings-services-dashboard-banner.html.twig';
import './sw-settings-services-dashboard-banner.scss';
import { getAssetFilter, getUserConfigService, wrapComponentConfig } from '../dependencies';

/**
 * @deprecated tag:v6.8.0 - Will be removed, the services banner is no longer shown on the dashboard.
 * @sw-package framework
 * @private
 */
export default wrapComponentConfig({
    name: 'sw-settings-services-dashboard-banner',

    template,

    data() {
        const assetFilter = getAssetFilter();

        return {
            isHidden: true,
            servicesGraphicLight: assetFilter(
                '/administration/administration/static/img/services/services-graphic-light.svg',
            ),
            servicesGraphicDark: assetFilter('/administration/administration/static/img/services/services-graphic-dark.svg'),
        };
    },

    created() {
        getUserConfigService()
            .search(['core.hide-services-dashboard-banner'])
            .then((response) => {
                const config = response?.data?.['core.hide-services-dashboard-banner'] as boolean[] | undefined;

                this.isHidden = config?.[0] ?? false;
            })
            .catch(() => {
                this.isHidden = false;
            });
    },

    methods: {
        async hideBanner() {
            await getUserConfigService().upsert({
                'core.hide-services-dashboard-banner': [true],
            });

            this.isHidden = true;
        },
    },
});
