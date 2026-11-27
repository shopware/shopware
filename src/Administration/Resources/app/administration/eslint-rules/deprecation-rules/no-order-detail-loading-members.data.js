/**
 * @sw-package framework
 *
 * The loading and editing state of the order detail page that 6.8 moves into the `swOrderDetail` Pinia store:
 * the provides, members, `router-view` attributes and events of `sw-order-detail` and its views, taken from the
 * `@deprecated tag:v6.8.0` markers. The getters `isLoading` and `isEditing` of the store itself stay.
 */

const SET_LOADING = "Shopware.Store.get('swOrderDetail').setLoading(['order', value])";
const SET_EDITING = "Shopware.Store.get('swOrderDetail').editing = value";

module.exports = {
    id: 'order-detail-store',
    description: 'Marks the order detail loading and editing members that 6.8 moves into the swOrderDetail store',
    members: [
        { name: 'swOrderDetailOnCreatedByIdChange', unique: true, message: 'The swOrderDetailOnCreatedByIdChange provide of sw-order-detail is removed without replacement. Read order.createdById instead.' },
        { name: 'swOrderDetailOnLoadingChange', unique: true, message: `The swOrderDetailOnLoadingChange provide of sw-order-detail is removed. Use ${SET_LOADING}.` },
        { name: 'swOrderDetailOnEditingChange', unique: true, message: `The swOrderDetailOnEditingChange provide of sw-order-detail is removed. Use ${SET_EDITING}.` },
        { name: 'updateCreatedById', unique: true, message: 'sw-order-detail.updateCreatedById() is removed without replacement. Read order.createdById instead.' },
        { name: 'onSaveAndReload', unique: true, message: 'sw-order-detail.onSaveAndReload() is removed. Use saveAndReload().' },
        { name: 'onUpdateEditing', unique: true, message: `sw-order-detail.onUpdateEditing() is removed. Use updateEditing(value) or ${SET_EDITING}.` },
        { component: 'sw-order-detail', name: 'isEditing', message: "sw-order-detail.isEditing is removed. Use the editing computed (Shopware.Store.get('swOrderDetail').editing)." },
        { component: 'sw-order-detail', name: 'createdById', message: 'sw-order-detail.createdById is removed without replacement. Read order.createdById instead.' },
        { component: 'sw-order-detail', name: 'isLoading', message: `sw-order-detail.isLoading is removed. Read loading.order and write with ${SET_LOADING}.` },
        { component: 'sw-order-detail', name: 'onChangeLanguage', kinds: ['methods'], message: 'sw-order-detail.onChangeLanguage() is removed without replacement. It did nothing.' },
        { component: 'sw-order-detail', name: 'onStartEditing', kinds: ['methods'], message: 'sw-order-detail.onStartEditing() is removed without replacement. It did nothing.' },
        { component: 'sw-order-detail', name: 'onUpdateLoading', message: `sw-order-detail.onUpdateLoading() is removed. Use ${SET_LOADING}.` },
        { component: 'sw-order-detail-general', name: 'isSaveSuccessful', message: "The isSaveSuccessful prop of sw-order-detail-general is removed without replacement. Read Shopware.Store.get('swOrderDetail').savedSuccessful instead." },
        { component: 'sw-order-detail-general', name: 'isLoading', message: "sw-order-detail-general.isLoading is removed. Use loading.order, or Shopware.Store.get('swOrderDetail').isLoading for any loading state." },
        { component: 'sw-order-detail-general', name: 'updateLoading', message: `sw-order-detail-general.updateLoading() is removed. Use ${SET_LOADING}.` },
        { component: 'sw-order-detail-details', name: 'isSaveSuccessful', message: "The isSaveSuccessful prop of sw-order-detail-details is removed without replacement. Read Shopware.Store.get('swOrderDetail').savedSuccessful instead." },
        { component: 'sw-order-detail-details', name: 'isLoading', message: "sw-order-detail-details.isLoading is removed. Use Shopware.Store.get('swOrderDetail').loading.order, or .isLoading for any loading state." },
        { component: 'sw-order-detail-details', name: 'updateLoading', message: `sw-order-detail-details.updateLoading() is removed. Use ${SET_LOADING}.` },
        { component: 'sw-order-detail-documents', name: 'isV68Active', message: "sw-order-detail-documents.isV68Active is removed without replacement. Use Shopware.Feature.isActive('v6.8.0.0') if you still need it." },
    ],
    events: [
        { component: ['sw-order-detail-general', 'sw-order-detail-details', 'sw-order-detail-documents'], name: 'loading-change', message: `sw-order-detail no longer listens to loading-change from its views. Use ${SET_LOADING}.` },
        { component: ['sw-order-detail-general', 'sw-order-detail-details', 'sw-order-detail-documents'], name: 'editing-change', message: `sw-order-detail no longer listens to editing-change from its views. Use ${SET_EDITING}.` },
        { component: ['sw-order-detail-general', 'sw-order-detail-details', 'sw-order-detail-documents'], name: 'created-by-id-change', message: 'sw-order-detail no longer listens to created-by-id-change from its views. Remove the event.' },
    ],
    /** Attributes of the `<router-view>` in block `sw_order_detail_content_view` of `sw-order-detail` (`@` for listeners). */
    routerView: {
        attributes: ['is-editing', 'is-save-successful', '@created-by-id-change', '@loading-change', '@editing-change'],
        message: "sw-order-detail no longer passes is-editing and is-save-successful to its router-view or listens to created-by-id-change, loading-change and editing-change. Use the swOrderDetail store (editing, savedSuccessful, setLoading(['order', value])).",
    },
};
