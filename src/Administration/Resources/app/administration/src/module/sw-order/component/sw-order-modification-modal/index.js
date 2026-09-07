import template from './sw-order-modification-modal.html.twig';

/**
 * @sw-package checkout
 *
 * Add/edit form for a single order_price_modification row. Splits the entity's signed `price` into
 * a local `magnitude` (always >= 0) and `direction` ('reduction' | 'surcharge') for friendlier
 * input, recombined into a signed `price` on save.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: [
        'modal-close',
        'modal-save',
    ],

    props: {
        modification: {
            type: Object,
            required: true,
        },
        order: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            magnitude: Math.abs(this.modification.price) || 0,
            direction: this.modification.price > 0 ? 'surcharge' : 'reduction',
            taxExempt: this.modification.taxRules === null,
            // Pre-select any already-persisted rate restriction.
            selectedTaxRates: Array.isArray(this.modification.taxRules)
                ? this.modification.taxRules.map((taxRule) => taxRule.taxRate)
                : [],
        };
    },

    computed: {
        isNew() {
            return this.modification.isNew();
        },

        // A row contributed by the checkout pipeline (has a `type`) had its tax treatment already
        // fixed at creation, so — unlike every other field — it stays locked permanently.
        isLocked() {
            return !!this.modification.type;
        },

        directionOptions() {
            return [
                {
                    value: 'reduction',
                    name: this.$t('sw-order.detailBase.modificationDirectionReduction'),
                },
                {
                    value: 'surcharge',
                    name: this.$t('sw-order.detailBase.modificationDirectionSurcharge'),
                },
            ];
        },

        isValid() {
            return !!this.modification.label && this.magnitude > 0 && !!this.direction;
        },

        // Just a helpful input-side guard — OrderPriceModificationProcessor already caps a
        // reduction server-side regardless of this value. Surcharges have no ceiling.
        maxMagnitude() {
            return this.direction === 'reduction' ? (this.order.price?.totalPrice ?? null) : null;
        },

        // Every distinct, non-zero tax rate on the order's line items — read from lineItems, not
        // order.price.calculatedTaxes, so a modification that fully consumes a rate doesn't make
        // that rate vanish from this list on re-edit.
        orderTaxRates() {
            const rates = new Set();

            (this.order.lineItems ?? []).forEach((lineItem) => {
                (lineItem.price?.calculatedTaxes ?? []).forEach((calculatedTax) => {
                    if (calculatedTax.tax !== 0 || calculatedTax.price !== 0) {
                        rates.add(calculatedTax.taxRate);
                    }
                });
            });

            return [...rates].sort((a, b) => a - b);
        },

        // Only worth offering with >1 rate, when not fully exempt, and not locked.
        showTaxRateSelection() {
            return !this.taxExempt && !this.isLocked && this.orderTaxRates.length > 1;
        },

        // sw-multi-select needs {value, label} options, not the bare rate numbers orderTaxRates holds.
        taxRateOptions() {
            return this.orderTaxRates.map((rate) => ({ value: rate, label: `${rate} %` }));
        },
    },

    methods: {
        onSave() {
            if (!this.isValid) {
                return;
            }

            this.modification.price = this.direction === 'reduction' ? -this.magnitude : this.magnitude;

            if (this.taxExempt) {
                this.modification.taxRules = null;
            } else if (this.showTaxRateSelection) {
                // The checklist is the source of truth whenever it's shown — an empty selection
                // means "unrestricted", exactly like leaving every box unchecked reads visually.
                this.modification.taxRules = this.selectedTaxRates.map((taxRate) => ({ taxRate, percentage: 100 }));
            } else {
                // No checklist to consult here — fall back to the frozen priceDefinition.taxRules
                // (never null) rather than the entity's own, which a prior exempt=true save nulled out.
                this.modification.taxRules = this.modification.taxRules ?? this.modification.priceDefinition?.taxRules ?? [];
            }

            this.$emit('modal-save', this.modification);
        },
    },
};
