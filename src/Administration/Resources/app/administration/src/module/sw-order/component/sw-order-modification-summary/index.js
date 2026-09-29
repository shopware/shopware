import template from './sw-order-modification-summary.html.twig';
import './sw-order-modification-summary.scss';

/**
 * @sw-package checkout
 *
 * Editable list of an order's order_price_modification rows. Kept inside the price-summary
 * <dt>/<dd> list rather than a sw-data-grid, so modifications read as summary rows, not a second
 * line-items table.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    emits: [
        'save-and-recalculate',
        'recalculate-and-reload',
    ],

    props: {
        order: {
            type: Object,
            required: true,
        },
        context: {
            type: Object,
            required: true,
        },
        editable: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            showFormFor: null,
            showDeleteModal: null,
        };
    },

    computed: {
        orderPriceModificationRepository() {
            return this.repositoryFactory.create('order_price_modification');
        },

        modifications() {
            return this.order.priceModifications ?? [];
        },

        currency() {
            return this.order.currency;
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },
    },

    methods: {
        currencyAmount(amount) {
            // Already signed to match display convention directly (negative reduces, positive
            // surcharges) — no negation needed.
            return this.currencyFilter(amount, this.currency.isoCode, this.order.totalRounding.decimals);
        },

        typeTooltip(modification) {
            // type/referencedId are freeform — label the raw values, don't assume plugin attribution.
            const lines = [];

            if (modification.type) {
                lines.push(this.$t('sw-order.detailBase.modificationTypeLine', { type: modification.type }));
            }

            if (modification.referencedId) {
                lines.push(
                    this.$t('sw-order.detailBase.modificationReferencedIdLine', { referencedId: modification.referencedId }),
                );
            }

            return lines.join('<br>');
        },

        // OrderPriceModificationProcessor recomputes a "percentage" row from its priceDefinition on
        // every recalculation, so editing its cached `price` here would have no effect — no
        // edit/delete affordance for it; only the contributing plugin's own UI should touch it.
        isPercentageType(modification) {
            return (modification.priceDefinition?.type ?? null) === 'percentage';
        },

        onAddModification() {
            this.showFormFor = this.orderPriceModificationRepository.create(this.context);
            this.showFormFor.orderId = this.order.id;
            this.showFormFor.price = 0;
            this.showFormFor.taxRules = [];
            this.showFormFor.position = this.modifications.length;
        },

        onEditModification(modification) {
            this.showFormFor = modification;
        },

        onCloseForm() {
            this.showFormFor = null;
        },

        onSaveModification(modification) {
            this.orderPriceModificationRepository.save(modification, this.context).then(() => {
                this.showFormFor = null;
                this.$emit('save-and-recalculate');
            });
        },

        onDeleteRequest(modification) {
            this.showDeleteModal = modification.id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = null;
        },

        onConfirmDelete() {
            const id = this.showDeleteModal;
            this.showDeleteModal = null;

            this.orderPriceModificationRepository.delete(id, this.context).then(() => {
                this.$emit('recalculate-and-reload');
            });
        },
    },
};
