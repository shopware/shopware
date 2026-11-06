/**
 * @sw-package framework
 */

import { componentMember, injectKey, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.order-detail-loading',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The order detail page and its views read the loading and editing state from the swOrderDetail store.',
    references: [upgradeGuide('UPGRADE-6.8.md#order-detail-loading-and-editing-moved-to-the-sworderdetail-store')],
    usage: [
        injectKey({
            from: 'swOrderDetailOnLoadingChange',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        injectKey({
            from: 'swOrderDetailOnEditingChange',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        injectKey({
            from: 'swOrderDetailOnCreatedByIdChange',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        componentMember({
            components: ['sw-order-detail'],
            member: 'isEditing',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        componentMember({
            components: ['sw-order-detail'],
            member: 'isLoading',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        componentMember({
            components: ['sw-order-detail'],
            member: 'createdById',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        componentMember({
            components: ['sw-order-detail'],
            member: 'updateCreatedById',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        componentMember({
            components: ['sw-order-detail'],
            member: 'onChangeLanguage',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        componentMember({
            components: ['sw-order-detail'],
            member: 'onStartEditing',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        componentMember({
            components: ['sw-order-detail'],
            member: 'onSaveAndReload',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        componentMember({
            components: ['sw-order-detail'],
            member: 'onUpdateLoading',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        componentMember({
            components: ['sw-order-detail'],
            member: 'onUpdateEditing',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        componentMember({
            components: ['sw-order-detail-general', 'sw-order-detail-details'],
            member: 'isLoading',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        componentMember({
            components: ['sw-order-detail-general', 'sw-order-detail-details'],
            member: 'isSaveSuccessful',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        componentMember({
            components: ['sw-order-detail-general', 'sw-order-detail-details'],
            member: 'updateLoading',
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
    ],
});
