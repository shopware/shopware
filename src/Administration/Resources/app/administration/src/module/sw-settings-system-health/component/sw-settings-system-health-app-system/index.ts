import template from './sw-settings-system-health-app-system.html.twig';
import { checkViewProps, useCheckView } from '../../use-check-view';
import { STATUS_COLORS, STATUS_LABELS, type SystemHealthStatus } from '../../types';

type AppHealth = { name: string; status: SystemHealthStatus; reason: string | null; lastStatusCode: number | null };

// apps with problems first
const SEVERITY: SystemHealthStatus[] = [
    'FAILURE',
    'ERROR',
    'WARNING',
    'UNKNOWN',
    'OK',
    'SKIPPED',
];

/**
 * The app connections check: one row per app with its status and the reason, problems first.
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: checkViewProps,

    setup: (props) => useCheckView(props),

    computed: {
        apps(): AppHealth[] {
            return [...((this.extra.apps ?? []) as AppHealth[])].sort(
                (a, b) => SEVERITY.indexOf(a.status) - SEVERITY.indexOf(b.status),
            );
        },

        columns(): Array<{ property: string; label: string }> {
            return [
                'name',
                'status',
                'reason',
                'lastStatusCode',
            ].map((property) => ({
                property,
                label: this.$t(`sw-settings-system-health.app-system.column.${property}`),
            }));
        },
    },

    methods: {
        statusLabel(status: SystemHealthStatus): string {
            return this.$t(STATUS_LABELS[status]);
        },

        statusColor(status: SystemHealthStatus): string {
            return STATUS_COLORS[status];
        },
    },
});
