import template from './sw-settings-system-health-administration.html.twig';
import { checkViewProps, useCheckView } from '../../use-check-view';

/**
 * The Administration readiness check: the request for the Administration start page, with the missing
 * build files as its error.
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: checkViewProps,

    setup: (props) => useCheckView(props),

    computed: {
        missingBundles(): string[] {
            return (this.extra.missingArtifactsForJsBundles ?? []) as string[];
        },

        summary(): string | null {
            if (this.missingBundles.length > 0) {
                return this.$t('sw-settings-system-health.administration.missing', {
                    bundles: this.missingBundles.join(', '),
                });
            }

            return this.extra.indexPageJsBundlesFound === 0
                ? this.$t('sw-settings-system-health.administration.no-scripts')
                : this.$t('sw-settings-system-health.administration.not-loading');
        },

        // the start page of the Administration, on the address it runs under
        url(): string {
            return `${window.location.origin}${window.location.pathname}`;
        },
    },
});
