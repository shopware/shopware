/**
 * @sw-package framework
 */
import template from './sw-settings-usage-data-user-data-consent-card.html.twig';
import '../sw-settings-usage-data-consent-modal-sub-cards.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,
    name: 'SwSettingsUsageDataUserDataConsentCard',

    emits: ['update:consent'],

    props: {
        consent: {
            type: Boolean,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
});
