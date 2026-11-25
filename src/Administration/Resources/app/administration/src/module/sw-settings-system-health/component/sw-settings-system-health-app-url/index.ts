import template from './sw-settings-system-health-app-url.html.twig';
import { checkViewProps, useCheckView } from '../../use-check-view';

/**
 * The APP_URL check: the verification request.
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: checkViewProps,

    setup: (props) => useCheckView(props),

    computed: {
        summary(): string | null {
            return this.status === 'WARNING' || this.status === 'ERROR'
                ? this.$t(`sw-settings-system-health.app-url.${this.status}`, { appUrl: this.extra.appUrl })
                : null;
        },
    },
});
