import { shallowMount } from '@vue/test-utils';
import swCustomerBaseInfo from 'src/module/sw-customer/component/sw-customer-base-info';

Shopware.Component.register('sw-customer-base-info', swCustomerBaseInfo);

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/order',
    status: 200,
    response: {
        data: [
            {
                id: '1'
            }
        ]
    }
});

responses.addResponse({
    method: 'Post',
    url: '/search/language',
    status: 200,
    response: {
        data: [
            {
                id: '1'
            }
        ]
    }
});

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-customer-base-info'), {
        propsData: {
            customer: {
                birthday: '1992-12-22T00:00:00.000+00:00',
                lastLogin: '2021-10-14T11:23:44.195+00:00',
                group: {
                    translated: {
                        name: 'Group test'
                    }
                },
                defaultPaymentMethod: {
                    translated: {
                        distinguishableName: 'Payment test'
                    }
                }
            },
            customerEditMode: false,
            isLoading: false
        },
        stubs: {
            'sw-container': true,
            'sw-loader': true,
            'sw-description-list': true,
            'sw-entity-single-select': true,
            'sw-checkbox-field': true,
            'sw-help-text': true,
            'sw-datepicker': true
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve({
                        total: 2,
                        aggregations: { orderAmount: { sum: 29.68 } }
                    }),
                    get: () => Promise.resolve(),
                })
            }
        }
    });
}

describe('module/sw-customer/page/sw-customer-base-info', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the birthday', async () => {
        expect(wrapper.find('.sw-customer-base__label-birthday').text()).toBe('22 December 1992');
    });

    it('should display the empty birthday snippet placeholder', async () => {
        await wrapper.setProps({
            customer: {
                ...wrapper.props().customer,
                birthday: null
            }
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-customer-base__label-birthday').text()).toBe('sw-customer.baseInfo.emptyTextBirthday');
    });

    it('should display the last login date', async () => {
        expect(wrapper.find('.sw-customer-base__label-last-login').text()).toBe('14 October 2021 at 11:23');
    });

    it('should display the last login snippet placeholder', async () => {
        await wrapper.setProps({
            customer: {
                ...wrapper.props().customer,
                lastLogin: null
            }
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-customer-base__label-last-login').text()).toBe('sw-customer.baseInfo.emptyTextLogin');
    });

    it('should display turnover of user', async () => {
        expect(wrapper.find('.sw-customer-base__label-turnover').text()).toBe('€29.68');
    });
});
