/**
 * @sw-package framework
 */

import { componentMembers, injectKey, jsApiMigration, upgradeGuide } from '../helpers';

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
        ...componentMembers({
            components: ['sw-order-detail'],
            members: [
                'isEditing',
                'isLoading',
                'createdById',
                'updateCreatedById',
                'onChangeLanguage',
                'onStartEditing',
                'onSaveAndReload',
                'onUpdateLoading',
                'onUpdateEditing',
            ],
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
        ...componentMembers({
            components: ['sw-order-detail-general', 'sw-order-detail-details'],
            members: ['isLoading', 'isSaveSuccessful', 'updateLoading'],
            to: "the swOrderDetail store (loading.order, setLoading(['order', value]), editing, savedSuccessful)",
        }),
    ],
});
