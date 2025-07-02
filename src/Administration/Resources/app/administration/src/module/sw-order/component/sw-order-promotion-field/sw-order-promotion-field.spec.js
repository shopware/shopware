import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */
const orderFixture = {
    id: '2720b2fa-2ddc-479b-8c93-864fc8978f77',
    versionId: '305d71dc-7e9d-4ce2-a563-ecf91edd9cb3',
    currency: {
        isoCode: 'EUR',
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

const manualPromotions = orderFixture.lineItems.filter((item) => item.type === 'promotion' && item.referencedId !== null);
const automaticPromotions = orderFixture.lineItems.filter((item) => item.type === 'promotion' && item.referencedId === null);

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
    Shopware.Store.get('swOrderDetail').$reset();
    Shopware.Store.get('swOrderDetail').order = { ...orderFixture, ...customOrder };
};

async function createWrapper(privileges = []) {
    const notificationMixin = {
        methods: {
            createNotificationError() {},
            createNotificationWarning() {},
            createNotificationSuccess() {},
        },
    };

    return mount(await wrapTestComponent('sw-order-promotion-field', { sync: true }), {
        props: {
            isLoading: false,
        },
        global: {
            stubs: {
                'sw-order-promotion-tag-field': true,
                'sw-modal': {
                    emits: ['modal-close'],
                    template: `<div class="sw-modal__content"><slot /></div>`,
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        delete: (promotionId) => {
                            createStateMapper({
                                lineItems: orderFixture.lineItems.filter((item) => promotionId !== item.id),
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
                    applyAutomaticPromotions: () => {
                        createStateMapper({
                            lineItems: [
                                ...orderFixture.lineItems,
                                {
                                    id: 'auto-applied-promotion',
                                    type: 'promotion',
                                    referencedId: null,
                                    payload: { code: '' },
                                },
                            ],
                        });

                        return Promise.resolve(successResponseForNotification);
                    },
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
        },
        mixins: [
            notificationMixin,
        ],
    });
}

describe('src/module/sw-order/component/sw-order-promotion-field', () => {
    it('should filter manual Promotions', async () => {
        createStateMapper();

        const wrapper = await createWrapper();

        expect(wrapper.vm.manualPromotions).toStrictEqual(manualPromotions);
    });

    /**
     * @deprecated tag:v6.8.0 - Will be removed.
     */
    it('should filter automatic Promotions', async () => {
        createStateMapper();
        const wrapper = await createWrapper();

        expect(wrapper.vm.automaticPromotions).toStrictEqual(automaticPromotions);
        expect(wrapper.vm.hasAutomaticPromotions).toBeTruthy();
    });

    /**
     * @deprecated tag:v6.8.0 - Will be removed.
     */
    it('should disable automatic promotion on toggle with saved changes', async () => {
        createStateMapper();

        const wrapper = await createWrapper();
        await wrapper.setData({
            hasOrderUnsavedChanges: false,
        });
        wrapper.vm.disabledAutoPromotions = true;

        await flushPromises();

        expect(wrapper.vm.hasAutomaticPromotions).toBeFalsy();
        expect(wrapper.vm.disabledAutoPromotions).toBeTruthy();
        expect(wrapper.emitted('error')).toBeUndefined();
        expect(wrapper.emitted('reload-entity-data')).toBeTruthy();
    });

    /**
     * @deprecated tag:v6.8.0 - Will be removed.
     */
    it('should skip disable automatic promotion on toggle with unsaved changes', async () => {
        createStateMapper();

        const wrapper = await createWrapper();
        await wrapper.setData({
            hasOrderUnsavedChanges: true,
        });
        wrapper.vm.disabledAutoPromotions = true;

        expect(wrapper.vm.hasAutomaticPromotions).toBeTruthy();
        expect(wrapper.vm.disabledAutoPromotions).toBeTruthy();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.disabledAutoPromotions).toBeFalsy();
        expect(wrapper.vm.hasAutomaticPromotions).toBeTruthy();

        await flushPromises();

        expect(wrapper.emitted('reload-entity-data')).toBeFalsy();
        expect(wrapper.emitted('error')).toBeUndefined();
    });

    it('should save versioned order before adding promotion code', async () => {
        createStateMapper();

        const wrapper = await createWrapper();
        wrapper.vm.swOrderDetailOnSaveAndReload = jest.fn((afterSaveFn) => afterSaveFn());

        await wrapper.vm.onSubmitCode('Redeem675');
        await flushPromises();

        expect(wrapper.vm.swOrderDetailOnSaveAndReload).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.promotionCodeTags).toEqual([
            { code: 'Redeem3456' },
            { code: 'Redeem23' },
            { code: 'Redeem675' },
        ]);
        expect(wrapper.emitted('reload-entity-data')).toBeTruthy();
        expect(wrapper.emitted('error')).toBeUndefined();
    });

    it('should save versioned order before applying automatic promotion code', async () => {
        createStateMapper();

        const wrapper = await createWrapper();
        wrapper.vm.swOrderDetailOnSaveAndReload = jest.fn((afterSaveFn) => afterSaveFn());

        await wrapper.vm.applyAutomaticPromotions();
        await flushPromises();

        const autoPromotions = wrapper.vm.order.lineItems
            .filter((item) => item.type === 'promotion' && item.referencedId === null)
            .map((item) => item.id);

        expect(wrapper.vm.swOrderDetailOnSaveAndReload).toHaveBeenCalledTimes(1);
        expect(autoPromotions).toEqual([
            '05b5decd-072f-437e-84a3-8be5fb5e5fa7',
            'auto-applied-promotion',
        ]);
        expect(wrapper.emitted('error')).toBeUndefined();
        expect(wrapper.emitted('reload-entity-data')).toBeTruthy();
    });

    it('should save versioned order before removing promotion code', async () => {
        createStateMapper();

        const wrapper = await createWrapper();
        wrapper.vm.swOrderDetailOnSaveAndReload = jest.fn();

        wrapper.vm.onRemoveExistingCode({ code: 'Redeem3456' });
        await flushPromises();

        expect(wrapper.vm.swOrderDetailOnSaveAndReload).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.promotionCodeTags).toEqual([{ code: 'Redeem23' }]);
        expect(wrapper.emitted('error')).toBeUndefined();
        expect(wrapper.emitted('reload-entity-data')).toBeTruthy();
    });

    it('should disable the fields with missing roles', async () => {
        createStateMapper();

        const wrapper = await createWrapper();

        expect(wrapper.find('sw-order-promotion-tag-field-stub').attributes('disabled')).toBe(String(true));
        expect(wrapper.findComponent('.mt-button').props('disabled')).toBe(true);
    });

    it('should enable the fields with roles', async () => {
        createStateMapper();

        const wrapper = await createWrapper(['order.editor']);

        expect(wrapper.find('sw-order-promotion-tag-field-stub').attributes('disabled')).toBeUndefined();
        expect(wrapper.findComponent('.mt-button').props('disabled')).toBeUndefined();
    });

    it('should open modal on errors', async () => {
        createStateMapper();

        const wrapper = await createWrapper(['order.editor']);

        expect(wrapper.find('.sw-modal__content').exists()).toBe(false);

        wrapper.vm.promotionUpdates = [
            {
                messageKey: 'promotion-discount-deleted',
                parameters: { name: 'Disabled Auto promo' },
            },
            {
                messageKey: 'promotion-discount-added',
                parameters: { name: 'New Auto promo' },
            },
        ];

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.promotionsRemoved).toEqual([wrapper.vm.promotionUpdates[0]]);
        expect(wrapper.vm.promotionsAdded).toEqual([wrapper.vm.promotionUpdates[1]]);

        const modal = wrapper.get('.sw-modal__content');
        expect(modal.text()).toContain('updatesModal.description');
        expect(modal.text()).toContain('updatesModal.promotionAddedTitle');
        expect(modal.text()).toContain('updatesModal.promotionRemovedTitle');
        expect(modal.text()).toContain('Disabled Auto promo');
        expect(modal.text()).toContain('New Auto promo');

        wrapper.vm.dismissPromotionUpdates();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-modal__content').exists()).toBe(false);
    });
});
