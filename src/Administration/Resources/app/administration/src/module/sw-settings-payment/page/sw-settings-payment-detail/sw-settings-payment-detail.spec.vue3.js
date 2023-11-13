import { mount } from '@vue/test-utils_v3';

/**
 * @package checkout
 */

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-settings-payment-detail', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $route: {
                    query: {
                        page: 1,
                        limit: 25,
                    },
                    params: {
                        id: '12312',
                    },
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return {
                                id: '1a2b3c',
                                name: 'Test settings-payment',
                                entity: 'settings-payment',
                                pluginId: '12321-a',
                            };
                        },
                        get: () => Promise.resolve({
                            id: '1a2b3c',
                            name: 'Test settings-payment',
                            entity: 'settings-payment',
                            pluginId: '12321-a',
                        }),
                        search: () => Promise.resolve({}),
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) { return true; }

                        return privileges.includes(identifier);
                    },
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
                feature: {
                    isActive: () => true,
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>
                    `,
                },
                'sw-button': true,
                'sw-button-process': true,
                'sw-language-switch': true,
                'sw-card-view': true,
                'sw-card': true,
                'sw-container': true,
                'sw-switch-field': true,
                'sw-number-field': true,
                'sw-text-field': true,
                'sw-language-info': true,
                'sw-upload-listener': true,
                'sw-media-upload-v2': true,
                'sw-plugin-box': true,
                'sw-textarea-field': true,
                'sw-select-rule-create': true,
                'sw-sidebar': true,
                'sw-sidebar-media-item': true,
                'sw-skeleton': true,
                'sw-context-menu-item': true,
            },
        },
    });
}

describe('module/sw-settings-payment/page/sw-settings-payment-detail', () => {
    const mockPaymentMethod = {
        name: 'Cash',
        id: '1000000000',
        pluginId: '01',
    };
    mockPaymentMethod.getEntityName = () => { return 'payment_method'; };

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            paymentMethod: mockPaymentMethod,
            isLoading: false,
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to save the settings-payment', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            paymentMethod: mockPaymentMethod,
            isLoading: false,
        });
        await flushPromises();

        const saveButton = wrapper.find('.sw-payment-detail__save-action');
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save the settings-payment', async () => {
        const wrapper = await createWrapper([
            'payment.editor',
        ]);
        await wrapper.setData({
            paymentMethod: mockPaymentMethod,
            isLoading: false,
        });
        await flushPromises();

        const saveButton = wrapper.find('.sw-payment-detail__save-action');
        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit payment fields', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            paymentMethod: mockPaymentMethod,
            isLoading: false,
        });
        await flushPromises();

        const nameField = wrapper.find('.sw-settings-payment-detail__field-name');
        const positionField = wrapper.find('.sw-settings-payment-detail__field-position');
        const commentField = wrapper.find('.sw-settings-payment-detail__description');
        const uploadField = wrapper.find('.sw-settings-payment-detail__logo-image-upload');
        const description = wrapper.find('.sw-settings-payment-detail__description');
        const activeField = wrapper.find('.sw-settings-payment-detail__field-active');
        const afterOrderField = wrapper.find('.sw-settings-payment-detail__field-after-order');
        const ruleField = wrapper.find('.sw-settings-payment-detail__field-availability-rule');

        expect(nameField.attributes().disabled).toBeTruthy();
        expect(positionField.attributes().disabled).toBeTruthy();
        expect(commentField.attributes().disabled).toBeTruthy();
        expect(uploadField.attributes().disabled).toBeTruthy();
        expect(description.attributes().disabled).toBeTruthy();
        expect(activeField.attributes().disabled).toBeTruthy();
        expect(afterOrderField.attributes().disabled).toBeTruthy();
        expect(ruleField.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit payment fields', async () => {
        const wrapper = await createWrapper([
            'payment.editor',
        ]);
        await wrapper.setData({
            paymentMethod: mockPaymentMethod,
            isLoading: false,
        });
        await flushPromises();

        const nameField = wrapper.find('.sw-settings-payment-detail__field-name');
        const positionField = wrapper.find('.sw-settings-payment-detail__field-position');
        const commentField = wrapper.find('.sw-settings-payment-detail__description');
        const uploadField = wrapper.find('.sw-settings-payment-detail__logo-image-upload');
        const description = wrapper.find('.sw-settings-payment-detail__description');
        const activeField = wrapper.find('.sw-settings-payment-detail__field-active');
        const afterOrderField = wrapper.find('.sw-settings-payment-detail__field-after-order');
        const ruleField = wrapper.find('.sw-settings-payment-detail__field-availability-rule');

        expect(nameField.attributes().disabled).toBeFalsy();

        expect(positionField.attributes().disabled).toBeFalsy();
        expect(commentField.attributes().disabled).toBeFalsy();
        expect(uploadField.attributes().disabled).toBeFalsy();
        expect(description.attributes().disabled).toBeFalsy();
        expect(activeField.attributes().disabled).toBeFalsy();
        expect(afterOrderField.attributes().disabled).toBeFalsy();
        expect(ruleField.attributes().disabled).toBeFalsy();
    });

    it('should add conditions association', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            paymentMethod: mockPaymentMethod,
            isLoading: false,
        });
        const criteria = wrapper.vm.ruleFilter;

        expect(criteria.associations[0].association).toBe('conditions');
    });
});
