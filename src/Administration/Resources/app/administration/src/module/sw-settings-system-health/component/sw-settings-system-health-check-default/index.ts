import template from './sw-settings-system-health-check-default.html.twig';
import { checkViewProps, useCheckView } from '../../use-check-view';
import type { SystemHealthValue } from '../sw-settings-system-health-values';
import shortDate from '../../short-date';

// RFC 3339 / ISO 8601 date-time, as checks return timestamps
const ISO_DATE = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/;

/**
 * The view of a check without its own view: its details as labeled values. A label comes from the snippet
 * sw-settings-system-health.extra.<check>.<key>, a value from sw-settings-system-health.extra.<check>.values.<value>;
 * without a snippet the key is split into words and the value shown as it is.
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: checkViewProps,

    setup: (props) => useCheckView(props),

    computed: {
        values(): SystemHealthValue[] {
            return Object.entries(this.extra)
                .filter(([, value]) => value !== null && value !== '')
                .map(([key, value]) => ({ key, label: this.label(key), value: this.format(value) }));
        },
    },

    methods: {
        label(key: string): string {
            const snippet = `sw-settings-system-health.extra.${this.check.name}.${key}`;
            if (this.$te(snippet)) {
                return this.$t(snippet);
            }

            const words = key
                .replace(/_/g, ' ')
                .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
                .toLowerCase();

            return words.charAt(0).toUpperCase() + words.slice(1);
        },

        format(value: unknown): string {
            if (typeof value === 'string' && ISO_DATE.test(value)) {
                return shortDate(value);
            }

            if (Array.isArray(value) && value.every((item) => typeof item !== 'object')) {
                return value.length > 0 ? value.join(', ') : '-';
            }

            if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean') {
                return JSON.stringify(value);
            }

            const text = String(value);
            const snippet = `sw-settings-system-health.extra.${this.check.name}.values.${text}`;

            return this.$te(snippet) ? this.$t(snippet) : text;
        },
    },
});
