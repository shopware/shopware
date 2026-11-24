import template from './sw-settings-system-health-response-code.html.twig';

/**
 * An HTTP response code as a status pill: green below 400, red from 400. Without a code it shows the known
 * outcome (OK / Failed), or "No response".
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        statusCode: {
            type: Number,
            required: false,
            default: null,
        },

        successful: {
            type: Boolean,
            required: false,
            default: null,
        },
    },

    computed: {
        color(): string {
            if (this.statusCode) {
                return this.statusCode >= 400 ? 'red' : 'green';
            }

            if (this.successful !== null) {
                return this.successful ? 'green' : 'red';
            }

            return 'gray';
        },

        text(): string {
            if (this.statusCode) {
                return String(this.statusCode);
            }

            if (this.successful !== null) {
                return this.$t(
                    this.successful ? 'sw-settings-system-health.request.ok' : 'sw-settings-system-health.request.failed',
                );
            }

            return this.$t('sw-settings-system-health.request.no-response');
        },
    },
});
