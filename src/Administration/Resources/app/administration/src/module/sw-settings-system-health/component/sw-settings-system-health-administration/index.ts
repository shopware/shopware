import template from './sw-settings-system-health-administration.html.twig';
import type { SystemHealthCheck } from '../../types';
import { presentedSummary } from '../../presenters';

/**
 * The Administration readiness check: the request for the Administration start page, with the missing build files
 * as its error.
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        check: {
            type: Object as PropType<SystemHealthCheck>,
            required: true,
        },
    },

    computed: {
        summary(): string | null {
            return presentedSummary(this.check, this.$t.bind(this) as Parameters<typeof presentedSummary>[1]);
        },

        // the check requests the Administration's start page; this is that page on the address the admin runs under
        url(): string {
            return `${window.location.origin}${window.location.pathname}`;
        },

        durationMs(): number | null {
            const seconds = this.check.result?.extra.indexResponseTime;

            return typeof seconds === 'number' ? seconds * 1000 : null;
        },

        error(): string | null {
            const missing = (this.check.result?.extra.missingArtifactsForJsBundles ?? []) as string[];

            return missing.length > 0
                ? this.$t('sw-settings-system-health.presenter.AdministrationReadiness.missing', {
                      bundles: missing.join(', '),
                  })
                : null;
        },
    },
});
