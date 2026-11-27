import { MtCollapsible, MtCollapsibleContent } from '@shopware-ag/meteor-component-library';
import template from './sw-settings-system-health-check-row.html.twig';
import './sw-settings-system-health-check-row.scss';
import { STATUS_COLORS, STATUS_LABELS, type SystemHealthCheck } from '../../types';
import shortDate from '../../short-date';

const DEFAULT_COMPONENT = 'sw-settings-system-health-check-default';

/**
 * One check as a collapsible row: status, name and message are always visible, the check's result view shows
 * when the row is open.
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    // not registered globally, so imported like the admin menu does
    components: {
        MtCollapsible,
        MtCollapsibleContent,
    },

    props: {
        check: {
            type: Object as PropType<SystemHealthCheck>,
            required: true,
        },

        isRunning: {
            type: Boolean,
            required: false,
            default: false,
        },

        // the page keeps one check open at a time
        isOpen: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    inject: ['systemHealthCheckRegistry'],

    emits: ['run', 'toggle'],

    computed: {
        // the snippet sw-settings-system-health.check.<name>, else the name split into words ("Locales readiness")
        label(): string {
            const key = `sw-settings-system-health.check.${this.check.name}`;
            if (this.$te(key)) {
                return this.$t(key);
            }

            const words = this.check.name.replace(/([a-z0-9])([A-Z])/g, '$1 $2').toLowerCase();

            return words.charAt(0).toUpperCase() + words.slice(1);
        },

        categoryName(): string {
            return this.$t(`sw-settings-system-health.category.${this.check.category}.name`);
        },

        categoryDescription(): string {
            return this.$t(`sw-settings-system-health.category.${this.check.category}.description`);
        },

        checkedAt(): string | null {
            return this.check.result ? shortDate(this.check.result.checkedAt) : null;
        },

        // a short admin-side message for checks that only return a technical one
        message(): string {
            if (!this.check.result) {
                return '';
            }

            const key = `sw-settings-system-health.message.${this.check.name}.${this.check.result.status}`;

            return this.$te(key) ? this.$t(key) : this.check.result.message;
        },

        statusLabel(): string {
            if (this.isRunning) {
                return 'sw-settings-system-health.status.RUNNING';
            }

            return this.check.result ? STATUS_LABELS[this.check.result.status] : 'sw-settings-system-health.status.NOT_RUN';
        },

        statusColor(): string {
            return this.check.result ? STATUS_COLORS[this.check.result.status] : 'gray';
        },

        hasDetails(): boolean {
            return Object.keys(this.check.result?.extra ?? {}).length > 0;
        },

        // a check that never ran offers to run it; one that ran shows its details, if it has any
        hasPanel(): boolean {
            return !this.check.result || this.hasDetails;
        },

        // the check's own component when one is registered, else the default view
        resultComponent(): string {
            return this.systemHealthCheckRegistry.get(this.check.name).component ?? DEFAULT_COMPONENT;
        },
    },

    methods: {
        toggle(): void {
            if (this.hasPanel) {
                this.$emit('toggle', this.check.name);
            }
        },
    },
});
