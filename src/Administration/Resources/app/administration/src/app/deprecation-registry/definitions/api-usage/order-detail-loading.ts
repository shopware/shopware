/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, injectKey, templateAttribute, upgradeGuide } from '../helpers';

const MESSAGE =
    'Order detail loading and editing moved to the swOrderDetail store in 6.8. ' +
    "Use Shopware.Store.get('swOrderDetail'): loading.order, setLoading(['order', value]), editing, savedSuccessful and saveAndReload().";

export default apiUsageMigration({
    id: 'api.sw-order-detail.loading-and-editing',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The order detail page and its views read the loading and editing state from the swOrderDetail store instead of passing it to each other.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#order-detail-loading-and-editing-moved-to-the-sworderdetail-store'),
    ],
    usage: [
        injectKey({
            names: [
                'swOrderDetailOnLoadingChange',
                'swOrderDetailOnEditingChange',
                'swOrderDetailOnCreatedByIdChange',
            ],
            message: MESSAGE,
        }),
        templateAttribute({
            components: ['sw-order-detail'],
            elements: ['router-view'],
            names: [
                'is-editing',
                'is-save-successful',
                '@created-by-id-change',
                '@loading-change',
                '@editing-change',
            ],
            message: MESSAGE,
        }),
        templateAttribute({
            elements: ['sw-order-promotion-field'],
            names: ['@loading-change'],
            message: MESSAGE,
        }),
        componentMember({
            components: ['sw-order-detail'],
            names: [
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
            message: MESSAGE,
        }),
        componentMember({
            components: [
                'sw-order-detail-general',
                'sw-order-detail-details',
            ],
            names: [
                'isLoading',
                'isSaveSuccessful',
                'updateLoading',
            ],
            message: MESSAGE,
        }),
    ],
});
