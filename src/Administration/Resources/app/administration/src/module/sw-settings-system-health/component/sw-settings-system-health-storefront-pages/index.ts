import template from './sw-settings-system-health-storefront-pages.html.twig';
import { checkViewProps, useCheckView } from '../../use-check-view';

/**
 * The storefront readiness checks: one request per storefront page the check loaded.
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: checkViewProps,

    setup: (props) => useCheckView(props),

    computed: {
        // the details hold one entry per requested page
        pages(): Array<{ storefrontUrl: string; responseCode: number; responseTime: number; errorMessage: string | null }> {
            return Object.values(this.extra).filter((value) => typeof value === 'object' && value !== null) as never[];
        },

        summary(): string {
            const failed = this.pages.filter((page) => page.responseCode >= 400 || !!page.errorMessage).length;

            return this.$t('sw-settings-system-health.storefront-pages.summary', { failed, total: this.pages.length });
        },
    },
});
