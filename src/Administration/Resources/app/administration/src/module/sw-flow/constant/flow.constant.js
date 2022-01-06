export const ACTION = Object.freeze({
    ADD_TAG: 'action.add.tag',
    ADD_ORDER_TAG: 'action.add.order.tag',
    ADD_CUSTOMER_TAG: 'action.add.customer.tag',
    REMOVE_TAG: 'action.remove.tag',
    REMOVE_ORDER_TAG: 'action.remove.order.tag',
    REMOVE_CUSTOMER_TAG: 'action.remove.customer.tag',
    SET_ORDER_STATE: 'action.set.order.state',
    GENERATE_DOCUMENT: 'action.generate.document',
    MAIL_SEND: 'action.mail.send',
    STOP_FLOW: 'action.stop.flow',
    SET_ORDER_CUSTOM_FIELD: 'action.set.order.custom.field',
    SET_CUSTOMER_CUSTOM_FIELD: 'action.set.customer.custom.field',
    SET_CUSTOMER_GROUP_CUSTOM_FIELD: 'action.set.customer.group.custom.field',
    CHANGE_CUSTOMER_GROUP: 'action.change.customer.group',
    CHANGE_CUSTOMER_STATUS: 'action.change.customer.status',
    ADD_CUSTOMER_AFFILIATE_AND_CAMPAIGN_CODE: 'action.add.customer.affiliate.and.campaign.code',
    ADD_ORDER_AFFILIATE_AND_CAMPAIGN_CODE: 'action.add.order.affiliate.and.campaign.code',
});

export const ACTION_TYPE = Object.freeze({
    ADD_TAG: 'action.add.entity.tag',
    REMOVE_TAG: 'action.remove.entity.tag',
    SET_CUSTOM_FIELD: 'action.set.entity.custom.field',
    ADD_AFFILIATE_AND_CAMPAIGN_CODE: 'action.add.entity.affiliate.and.campaign.code',
});

export default {
    ACTION, ACTION_TYPE,
};
