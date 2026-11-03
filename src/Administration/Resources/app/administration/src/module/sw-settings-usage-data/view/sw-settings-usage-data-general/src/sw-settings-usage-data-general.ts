import template from './sw-settings-usage-data-general.html.twig';
import { defineAsyncComponent, wrapComponentConfig } from '../dependencies';
import SwSettingsUsageDataStoreDataConsent from './sw-settings-usage-data-store-data-consent/private';

/**
 * @sw-package data-services
 *
 * @private
 */
export default wrapComponentConfig({
    name: 'sw-settings-usage-data-general',

    template,

    components: {
        SwSettingsUsageDataStoreDataConsent: defineAsyncComponent(SwSettingsUsageDataStoreDataConsent.load),
    },
});
