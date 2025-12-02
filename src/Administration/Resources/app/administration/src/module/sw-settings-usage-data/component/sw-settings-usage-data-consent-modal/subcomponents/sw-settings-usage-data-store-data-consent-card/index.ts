/**
 * @sw-package framework
 */
import template from './sw-settings-usage-data-store-data-consent-card.html.twig';
import '../sw-settings-usage-data-consent-modal-sub-cards.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['update:consent'],

    props: {
        consent: {
            type: Boolean,
            required: true,
        },
    },
});
