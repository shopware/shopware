import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-customer/page/sw-customer-detail';
import 'src/app/component/base/sw-button';
import 'src/app/component/form/sw-custom-field-set-renderer';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/utils/sw-inherit-wrapper';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-customer-detail'), {
        mocks: {
            $tc: () => {
            },
            $route: {
                query: {
                    edit: false
                }
            }
        },

        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        get: () => Promise.resolve({
                            id: 'test',
                            requestedGroup: {
                                translated: {
                                    name: 'Test'
                                }
                            }
                        })
                    };
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
            customerId: 'test',
            customer: {}
        },

        stubs: {
            'sw-card-view': '<div><slot></slot></div>',
            'sw-alert': '<div><slot></slot></div>',
            'sw-button': Shopware.Component.build('sw-button'),
            'router-view': true,
            'sw-page': '<div><slot name="content"></slot></div>',
            'sw-card': '<div><slot></slot></div>',
            'sw-customer-card': '<div></div>',
            'sw-custom-field-set-renderer': Shopware.Component.build('sw-custom-field-set-renderer'),
            'sw-tabs': '<div><slot name="content"></slot></div>',
            'sw-form-field-renderer': Shopware.Component.build('sw-form-field-renderer'),
            'sw-field': '<div></div>',
            'sw-inherit-wrapper': Shopware.Component.build('sw-inherit-wrapper')
        }
    });
}

describe('module/sw-customer/view/sw-customer-detail-base.spec.js', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should accept customer registration button called', async () => {
        expect(wrapper.vm.customerGroupRegistrationService.decline).not.toHaveBeenCalled();
        expect(wrapper.vm.customerGroupRegistrationService.accept).not.toHaveBeenCalled();

        const button = wrapper.find('.sw-customer-detail__customer-registration-alert button:last-child');
        await button.trigger('click');

        expect(wrapper.vm.customerGroupRegistrationService.accept).toHaveBeenCalled();
    });

    it('should decline customer registration button called', async () => {
        expect(wrapper.vm.customerGroupRegistrationService.decline).not.toHaveBeenCalled();
        expect(wrapper.vm.customerGroupRegistrationService.accept).not.toHaveBeenCalled();

        const button = wrapper.find('.sw-customer-detail__customer-registration-alert button:first-child');
        await button.trigger('click');

        expect(wrapper.vm.customerGroupRegistrationService.decline).toHaveBeenCalled();
    });
});
