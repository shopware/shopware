/**
 * @private
 * @package business-ops
 */
export const ACTION = Object.freeze({
    ADD_TAG: 'action.add.tag',
    ADD_ORDER_TAG: 'action.add.order.tag',
    ADD_CUSTOMER_TAG: 'action.add.customer.tag',
    REMOVE_TAG: 'action.remove.tag',
    REMOVE_ORDER_TAG: 'action.remove.order.tag',
    REMOVE_CUSTOMER_TAG: 'action.remove.customer.tag',
    SET_ORDER_STATE: 'action.set.order.state',
    GENERATE_DOCUMENT: 'action.generate.document',
    GRANT_DOWNLOAD_ACCESS: 'action.grant.download.access',
    MAIL_SEND: 'action.mail.send',
    STOP_FLOW: 'action.stop.flow',
    SET_ORDER_CUSTOM_FIELD: 'action.set.order.custom.field',
    SET_CUSTOMER_CUSTOM_FIELD: 'action.set.customer.custom.field',
    SET_CUSTOMER_GROUP_CUSTOM_FIELD: 'action.set.customer.group.custom.field',
    CHANGE_CUSTOMER_GROUP: 'action.change.customer.group',
    CHANGE_CUSTOMER_STATUS: 'action.change.customer.status',
    ADD_CUSTOMER_AFFILIATE_AND_CAMPAIGN_CODE: 'action.add.customer.affiliate.and.campaign.code',
    ADD_ORDER_AFFILIATE_AND_CAMPAIGN_CODE: 'action.add.order.affiliate.and.campaign.code',
    APP_FLOW_ACTION: 'action.app.flow',
});

/**
 * @private
 * @package business-ops
 */
export const ACTION_TYPE = Object.freeze({
    ADD_TAG: 'action.add.entity.tag',
    REMOVE_TAG: 'action.remove.entity.tag',
    SET_CUSTOM_FIELD: 'action.set.entity.custom.field',
    ADD_AFFILIATE_AND_CAMPAIGN_CODE: 'action.add.entity.affiliate.and.campaign.code',
});

/**
 * @private
 * @package business-ops
 */
export const GENERAL_GROUP = 'general';
/**
 * @private
 * @package business-ops
 */
export const TAG_GROUP = 'tag';
/**
 * @private
 * @package business-ops
 */
export const CUSTOMER_GROUP = 'customer';
/**
 * @private
 * @package business-ops
 */
export const ORDER_GROUP = 'order';

/**
 * @private
 * @package business-ops
 */
export const ACTION_GROUP = Object.freeze({
    [ACTION.ADD_ORDER_TAG]: TAG_GROUP,
    [ACTION.ADD_CUSTOMER_TAG]: TAG_GROUP,
    [ACTION.REMOVE_ORDER_TAG]: TAG_GROUP,
    [ACTION.REMOVE_CUSTOMER_TAG]: TAG_GROUP,
    [ACTION.CHANGE_CUSTOMER_GROUP]: CUSTOMER_GROUP,
    [ACTION.CHANGE_CUSTOMER_STATUS]: CUSTOMER_GROUP,
    [ACTION.SET_CUSTOMER_CUSTOM_FIELD]: CUSTOMER_GROUP,
    [ACTION.SET_CUSTOMER_GROUP_CUSTOM_FIELD]: CUSTOMER_GROUP,
    [ACTION.ADD_CUSTOMER_AFFILIATE_AND_CAMPAIGN_CODE]: CUSTOMER_GROUP,
    [ACTION.ADD_ORDER_AFFILIATE_AND_CAMPAIGN_CODE]: CUSTOMER_GROUP,
    [ACTION.SET_ORDER_CUSTOM_FIELD]: ORDER_GROUP,
    [ACTION.GRANT_DOWNLOAD_ACCESS]: ORDER_GROUP,
    [ACTION.GENERATE_DOCUMENT]: GENERAL_GROUP,
    [ACTION.MAIL_SEND]: GENERAL_GROUP,
    [ACTION.STOP_FLOW]: GENERAL_GROUP,
});

/**
 * @private
 * @package business-ops
 */
export const GROUPS = [
    TAG_GROUP,
    CUSTOMER_GROUP,
    ORDER_GROUP,
    GENERAL_GROUP,
];

/**
 * @private
 * @package business-ops
 */
export default {
    ACTION,
    ACTION_TYPE,
    ACTION_GROUP,
    GROUPS,
    GENERAL_GROUP,
};
