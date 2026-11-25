import template from './sw-settings-system-health-check-details.html.twig';
import { needsAttention, type SystemHealthCheck } from '../../types';

/**
 * The details of a check result, the same for every check. A check that needs attention and has a resolution
 * snippet (sw-settings-system-health.resolution.<check>.impact) gets "What happened" and "What to do" first, with
 * the action that fixes it: its registered resolve route, or the `action` slot. The default slot holds the
 * check's evidence and is shown under "Details".
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['systemHealthCheckRegistry'],

    props: {
        check: {
            type: Object as PropType<SystemHealthCheck>,
            required: true,
        },

        // what happened, specific to this result
        summary: {
            type: String,
            required: false,
            default: null,
        },

        // what to do, when it depends on the result; else the snippet sw-settings-system-health.resolution.<check>.impact
        impact: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        snippetPrefix(): string {
            return `sw-settings-system-health.resolution.${this.check.name}`;
        },

        showsResolution(): boolean {
            return needsAttention(this.check) && (!!this.impact || this.$te(`${this.snippetPrefix}.impact`));
        },

        impactText(): string {
            return this.impact ?? this.$t(`${this.snippetPrefix}.impact`);
        },

        resolveRoute(): string | null {
            return this.systemHealthCheckRegistry.get(this.check.name).resolveRoute ?? null;
        },

        actionLabel(): string {
            const key = `${this.snippetPrefix}.action`;

            return this.$te(key) ? this.$t(key) : this.$t('sw-settings-system-health.resolution.resolve');
        },

        // the action sits on the first line of the panel: "What happened", or "What to do" without a summary
        actionOnTop(): boolean {
            return !!this.summary;
        },
    },

    methods: {
        resolve(): void {
            if (this.resolveRoute) {
                void this.$router.push({ name: this.resolveRoute });
            }
        },
    },
});
