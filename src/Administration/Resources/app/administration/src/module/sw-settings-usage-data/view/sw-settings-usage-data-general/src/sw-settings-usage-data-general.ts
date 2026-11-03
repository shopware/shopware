import template from './sw-settings-usage-data-general.html.twig';
import { SwSettingsUsageDataStoreDataConsent, wrapComponentConfig } from '../dependencies';

/**
 * @sw-package data-services
 *
 * @private
 */
export default wrapComponentConfig({
    name: 'sw-settings-usage-data-general',

    template,

    components: {
        SwSettingsUsageDataStoreDataConsent,
    },
});
