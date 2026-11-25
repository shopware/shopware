import template from './sw-settings-system-health-shop-id.html.twig';
import { checkViewProps, useCheckView } from '../../use-check-view';

type Fingerprint = { identifier: string; storedValue: string | null; currentValue: string; matches: boolean };

// a hash such as the sales channel domains fingerprint stays unreadable, so it is shortened
function shorten(value: string | null): string {
    if (!value) {
        return '-';
    }

    return /^[0-9a-f]{32,}$/.test(value) ? `${value.slice(0, 8)}…` : value;
}

/**
 * The shop identity check, like `bin/console app:shop-id:check`: every fingerprint with its stored and current
 * value, the apps on hold, and the shop ID change dialog to resolve it.
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['shopIdChangeService'],

    props: checkViewProps,

    setup: (props) => useCheckView(props),

    data(): { shopIdCheck: unknown; isOpening: boolean } {
        return {
            shopIdCheck: null,
            isOpening: false,
        };
    },

    computed: {
        fingerprints(): Fingerprint[] {
            return (this.extra.fingerprints ?? []) as Fingerprint[];
        },

        appsOnHold(): string[] {
            return (this.extra.appsOnHold ?? []) as string[];
        },

        summary(): string | null {
            if (this.extra.error) {
                return this.$t('sw-settings-system-health.shop-id.app-url-missing');
            }

            const changed = this.fingerprints.find((fingerprint) => !fingerprint.matches);
            if (!changed) {
                return null;
            }

            const key = `sw-settings-system-health.shop-id.changed.${changed.identifier}`;
            const values = { stored: changed.storedValue ?? '-', current: changed.currentValue };

            return this.$te(key) ? this.$t(key, values) : this.$t('sw-settings-system-health.shop-id.changed.other');
        },

        impact(): string {
            return this.appsOnHold.length > 0
                ? this.$t('sw-settings-system-health.shop-id.apps-on-hold', { apps: this.appsOnHold.join(', ') })
                : this.$t('sw-settings-system-health.shop-id.no-apps-on-hold');
        },

        // the fingerprints as the table shows them; the shop ID change dialog already names them
        rows(): Fingerprint[] {
            return this.fingerprints.map((fingerprint) => {
                const label = `sw-app.component.sw-app-shop-id-change-modal.fingerprints.${fingerprint.identifier}.label`;

                return {
                    identifier: this.$te(label) ? this.$t(label) : fingerprint.identifier,
                    storedValue: shorten(fingerprint.storedValue),
                    currentValue: shorten(fingerprint.currentValue),
                    matches: fingerprint.matches,
                };
            });
        },

        columns(): Array<{ property: string; label: string }> {
            return [
                'identifier',
                'storedValue',
                'currentValue',
                'matches',
            ].map((property) => ({
                property,
                label: this.$t(`sw-settings-system-health.shop-id.column.${property}`),
            }));
        },
    },

    methods: {
        async openResolveDialog(): Promise<void> {
            this.isOpening = true;

            try {
                this.shopIdCheck = await this.shopIdChangeService.checkShopId();
            } finally {
                this.isOpening = false;
            }
        },
    },
});
