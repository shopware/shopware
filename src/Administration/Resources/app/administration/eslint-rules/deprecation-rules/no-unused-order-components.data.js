/**
 * @sw-package framework
 *
 * The order components that 6.8 removes because the core no longer uses them, with the marker text for their tags and
 * for `Component.override/extend/build` of them. Taken from the `@deprecated tag:v6.8.0` markers. The root block
 * names are not used, because `sw_order_create` is also the root block of the page `sw-order-create`.
 */

const CREATE_FLOW = 'The order create page uses sw-order-create-initial, sw-order-create-general and sw-order-create-details.';
const STATE_HISTORY = 'The order detail page shows the state history in sw-order-state-history-modal.';

module.exports = {
    id: 'order-unused-components',
    description: 'Marks the order create and state history components that 6.8 removes without replacement',
    components: {
        'sw-order-create-base': `sw-order-create-base is removed without replacement. ${CREATE_FLOW}`,
        'sw-order-create-details-header': `sw-order-create-details-header is removed without replacement. ${CREATE_FLOW}`,
        'sw-order-create-details-body': `sw-order-create-details-body is removed without replacement. ${CREATE_FLOW}`,
        'sw-order-create-details-footer': `sw-order-create-details-footer is removed without replacement. ${CREATE_FLOW}`,
        'sw-order-create-address-modal': 'sw-order-create-address-modal is removed without replacement. sw-order-create-details selects addresses with sw-order-customer-address-select.',
        'sw-order-state-history-card': `sw-order-state-history-card is removed without replacement. ${STATE_HISTORY}`,
        'sw-order-state-history-card-entry': `sw-order-state-history-card-entry is removed without replacement. ${STATE_HISTORY}`,
        'sw-order-user-card': 'sw-order-user-card is removed without replacement. The order detail page shows the customer in sw-order-general-info and sw-order-detail-details.',
    },
};
