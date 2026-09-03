import { isAirGapped } from 'src/core/helper/air-gapped.helper';
import template from './sw-settings-usage-data-general.html.twig';
import SwSettingsUsageDataStoreDataConsent from '../../component/sw-settings-usage-data-store-data-consent';

/**
 * @sw-package data-services
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-usage-data-general',

    template,

    components: {
        SwSettingsUsageDataStoreDataConsent,
    },

    computed: {
        airGapped() {
            return isAirGapped();
        },
    },
});
