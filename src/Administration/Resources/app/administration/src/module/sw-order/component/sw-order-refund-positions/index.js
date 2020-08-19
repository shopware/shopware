import template from './sw-order-refund-positions.html.twig';

const { Component } = Shopware;

Component.register('sw-order-refund-positions', {
    template,

    props: {
        order: {
            type: Object,
            required: true
        },
        orderRefundPositions: {
            type: Array,
            required: true
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        orderRefundPositionColumns() {
            return [
                {
                    property: 'label',
                    label: this.$tc('sw-order.refundCard.createRefundModal.columns.product'),
                    rawData: true
                },
                {
                    property: 'refundQuantity',
                    label: this.$tc('sw-order.refundCard.createRefundModal.columns.quantity'),
                    rawData: true
                },
                {
                    property: 'refundUnitPrice',
                    label: this.$tc('sw-order.refundCard.createRefundModal.columns.unitPrice'),
                    rawData: true,
                    align: 'right'
                },
                {
                    property: 'refundTotalPrice',
                    label: this.$tc('sw-order.refundCard.createRefundModal.columns.totalPrice'),
                    rawData: true,
                    align: 'right'
                }
            ];
        }
    },

    methods: {
        onSelectItem(_, item, selected) {
            this.$emit('select-item', item.id, selected);
        },

        onChangeQuantity(value, id) {
            this.$emit('change-quantity', id, value);
        }
    }
});
