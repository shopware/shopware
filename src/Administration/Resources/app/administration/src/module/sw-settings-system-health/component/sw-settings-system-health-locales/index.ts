import template from './sw-settings-system-health-locales.html.twig';
import { checkViewProps, useCheckView } from '../../use-check-view';

/**
 * The locales readiness check: the invalid locale codes.
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: checkViewProps,

    setup: (props) => useCheckView(props),

    computed: {
        // the details are the invalid locale codes, keyed by language id
        codes(): string[] {
            return Object.values(this.extra) as string[];
        },

        summary(): string | null {
            return this.codes.length > 0
                ? this.$t('sw-settings-system-health.locales.summary', { count: this.codes.length }, this.codes.length)
                : null;
        },
    },
});
