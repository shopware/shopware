import template from './sw-settings-system-health-app-url.html.twig';
import type { SystemHealthCheck } from '../../types';
import { presentedSummary } from '../../presenters';

/**
 * The APP_URL check: its verification request.
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

        appUrl(): string | null {
            return (this.check.result?.extra.appUrl as string | undefined) ?? null;
        },

        statusCode(): number | null {
            return (this.check.result?.extra.statusCode as number | null | undefined) ?? null;
        },

        info(): string | null {
            return (this.check.result?.extra.info as string | null | undefined) ?? null;
        },
    },
});
