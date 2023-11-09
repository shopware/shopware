import { shallowMount } from '@vue/test-utils';
import swOrderPromotionField from 'src/module/sw-order/component/sw-order-promotion-field';

/**
 * @package checkout
 */

const orderFixture = {
    id: '2720b2fa-2ddc-479b-8c93-864fc8978f77',
    versionId: '305d71dc-7e9d-4ce2-a563-ecf91edd9cb3',
    currency: {
        shortName: 'EUR',
        symbol: '€',
    },
    lineItems: [
        {
            id: 'a4b4b1cf-95a7-4050-981b-0a1f301f5727',
            type: 'promotion',
            referencedId: '50669d0c-b1d2-470a-bb80-ac5ffa06ef10',
            payload: {
                code: 'Redeem3456',
            },
        },
        {
            id: '6066b693-97ce-4b91-a3e2-e015f0ddfb79',
            type: 'promotion',
            referencedId: 'f13ed3d3-158b-4fdf-bd54-d6fa8b880b83',
            payload: {
                code: 'Redeem23',
            },
        },
        {
            id: '05b5decd-072f-437e-84a3-8be5fb5e5fa7',
            type: 'promotion',
            referencedId: null,
            payload: {
                code: null,
            },
        },
    ],
};

const manualPromotions = orderFixture.lineItems.filter(item => item.type === 'promotion' && item.referencedId !== null);
const automaticPromotions = orderFixture.lineItems.filter(item => item.type === 'promotion' && item.referencedId === null);

const successResponseForNotification = {
    data: {
        errors: [
            {
                message: 'success',
            },
        ],
    },
};

const createStateMapper = (customOrder = {}) => {
    if (Shopware.State.list().includes('swOrderDetail')) {
        Shopware.State.unregisterModule('swOrderDetail');
    }

    const newModule = {
        state: {
            order: {
                ...orderFixture,
                ...customOrder,
            },
        },
    };

    Shopware.State.registerModule('swOrderDetail', {
        ...{
            namespaced: true,
            state: {
                isLoading: false,
                isSavedSuccessful: false,
                versionContext: {},
                order: orderFixture,
            },
        },
        ...newModule,
    });
};

const createWrapper = async (custom) => {
    const notificationMixin = {
        methods: {
            createNotificationError() {},
            createNotificationWarning() {},
            createNotificationSuccess() {},
        },
    };

    return shallowMount(await Shopware.Component.build('sw-order-promotion-field'), {
        ...{
            propsData: {
                isLoading: false,
            },
            stubs: {
                'sw-order-promotion-tag-field': true,
                'sw-switch-field': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        syncDeleted: (promotionIds) => {
                            createStateMapper({
                                lineItems: orderFixture.lineItems.filter(item => !promotionIds.includes(item.id)),
                            });

                            return Promise.resolve(successResponseForNotification);
                        },
                        delete: (promotionId) => {
                            createStateMapper({
                                lineItems: orderFixture.lineItems.filter(item => promotionId !== item.id),
                            });

                            return Promise.resolve(successResponseForNotification);
                        },
                    }),
                },
                orderService: {
                    toggleAutomaticPromotions: () => {
                        return Promise.resolve(successResponseForNotification);
                    },
                    addPromotionToOrder: (orderId, orderVersionId, code) => {
                        createStateMapper({
                            lineItems: [
                                ...orderFixture.lineItems,
                                {
                                    id: `this-is-id-${code}`,
                                    type: 'promotion',
                                    referencedId: `this-is-reference-id-${code}`,
                                    payload: {
                                        code: code,
                                    },
                                },
                            ],
                        });

                        return Promise.resolve(successResponseForNotification);
                    },
                },
                acl: {
                    can: () => {
                        return true;
                    },
                },
            },
            computed: {
                hasOrderUnsavedChanges() {
                    return false;
                },
            },
            mixins: [
                notificationMixin,
            ],
        },
        ...custom,
    });
};

Shopware.Component.register('sw-order-promotion-field', swOrderPromotionField);

describe('src/module/sw-order/component/sw-order-promotion-field', () => {
    it('should be a Vue.js component', async () => {
        createStateMapper();
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should filter manual Promotions', async () => {
        createStateMapper();
        const wrapper = await createWrapper();

        expect(wrapper.vm.manualPromotions).toStrictEqual(manualPromotions);
    });

    it('should filter automatic Promotions', async () => {
        createStateMapper();
        const wrapper = await createWrapper();

        expect(wrapper.vm.automaticPromotions).toStrictEqual(automaticPromotions);
        expect(wrapper.vm.hasAutomaticPromotions).toBeTruthy();
    });

    it('should disable automatic promotion on toggle with saved changes', async () => {
        createStateMapper();
        const wrapper = await createWrapper();

        wrapper.vm.disabledAutoPromotions = true;

        await flushPromises();

        expect(wrapper.vm.hasAutomaticPromotions).toBeFalsy();
        expect(wrapper.vm.disabledAutoPromotions).toBeTruthy();
        expect(wrapper.emitted('error')).toBeUndefined();
        expect(wrapper.emitted('reload-entity-data')).toBeTruthy();
    });

    it('should skip disable automatic promotion on toggle with unsaved changes', async () => {
        createStateMapper();
        const wrapper = await createWrapper({
            computed: {
                hasOrderUnsavedChanges() {
                    return true;
                },
            },
        });

        wrapper.vm.disabledAutoPromotions = true;

        expect(wrapper.vm.hasAutomaticPromotions).toBeTruthy();
        expect(wrapper.vm.disabledAutoPromotions).toBeTruthy();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.disabledAutoPromotions).toBeFalsy();
        expect(wrapper.vm.hasAutomaticPromotions).toBeTruthy();

        await flushPromises();

        expect(wrapper.emitted('reload-entity-data')).toBeFalsy();
        expect(wrapper.emitted('error')).toBeUndefined();
    });

    it('should skip adding promotion code with unsaved changes', async () => {
        createStateMapper();
        const wrapper = await createWrapper({
            computed: {
                hasOrderUnsavedChanges() {
                    return true;
                },
            },
        });

        wrapper.vm.onSubmitCode('Redeem675');

        await flushPromises();

        expect(wrapper.vm.promotionCodeTags).toEqual([{ code: 'Redeem3456' }, { code: 'Redeem23' }]);
        expect(wrapper.emitted('reload-entity-data')).toBeFalsy();
        expect(wrapper.emitted('error')).toBeUndefined();
    });

    it('should adding promotion code with saved changes', async () => {
        createStateMapper();
        const wrapper = await createWrapper();

        wrapper.vm.onSubmitCode('Redeem675');
        await flushPromises();

        expect(wrapper.vm.promotionCodeTags).toEqual([{ code: 'Redeem3456' }, { code: 'Redeem23' }, { code: 'Redeem675' }]);
        expect(wrapper.emitted('error')).toBeUndefined();
        expect(wrapper.emitted('reload-entity-data')).toBeTruthy();
    });

    it('should skip remove promotion code with unsaved changes', async () => {
        createStateMapper();

        const wrapper = await createWrapper({
            computed: {
                hasOrderUnsavedChanges() {
                    return true;
                },
            },
        });

        wrapper.vm.onRemoveExistingCode({ code: 'Redeem3456' });
        await flushPromises();

        expect(wrapper.vm.promotionCodeTags).toEqual([{ code: 'Redeem3456' }, { code: 'Redeem23' }]);
        expect(wrapper.emitted('error')).toBeUndefined();
        expect(wrapper.emitted('reload-entity-data')).toBeFalsy();
    });

    it('should remove promotion code with saved changes', async () => {
        createStateMapper();

        const wrapper = await createWrapper();

        wrapper.vm.onRemoveExistingCode({ code: 'Redeem3456' });
        await flushPromises();

        expect(wrapper.vm.promotionCodeTags).toEqual([{ code: 'Redeem23' }]);
        expect(wrapper.emitted('error')).toBeUndefined();
        expect(wrapper.emitted('reload-entity-data')).toBeTruthy();
    });
});
