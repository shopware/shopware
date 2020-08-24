import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-customer/page/sw-customer-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-customer-detail'), {
        localVue,
        mocks: {
            $tc: () => {},
            $device: {
                getSystemKey: () => {}
            },
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            }
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        return {
                            id: '1a2b3c',
                            name: 'Test customer',
                            entity: 'customer'
                        };
                    },
                    get: () => Promise.resolve({
                        id: '1a2b3c',
                        name: 'Test customer',
                        entity: 'customer'
                    }),
                    search: () => Promise.resolve({})
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            customerGroupRegistrationService: {
                accept: jest.fn().mockResolvedValue(true),
                decline: jest.fn().mockResolvedValue(true)
            },
            systemConfigApiService: {
                getValues: () => Promise.resolve([])
            }
        },
        propsData: {
            customerEditMode: false,
            customer: {},
            customerId: '1234321'
        },
        stubs: {
            'sw-page': `
                <div class="sw-page">
                    <slot name="smart-bar-actions"></slot>
                    <slot name="content">CONTENT</slot>
                    <slot></slot>
                </div>`,
            'sw-button': true,
            'sw-button-process': true,
            'sw-language-switch': true,
            'sw-card-view': true,
            'sw-card': true,
            'sw-container': true,
            'sw-field': true,
            'sw-language-info': true,
            'sw-tabs': true,
            'sw-tabs-item': true,
            'router-view': true
        }
    });
}

describe('module/sw-customer/page/sw-customer-detail', () => {
    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should not be able to edit the customer', async () => {
        const wrapper = createWrapper();
        wrapper.setData({
            isLoading: false
        });

        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-customer-detail__open-edit-mode-action');

        expect(saveButton.attributes().isLoading).toBeFalsy();
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit the customer', async () => {
        const wrapper = createWrapper([
            'customer.editor'
        ]);
        wrapper.setData({
            isLoading: false
        });
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-customer-detail__open-edit-mode-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });
});
