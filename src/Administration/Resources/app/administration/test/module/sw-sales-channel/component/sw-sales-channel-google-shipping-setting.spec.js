import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-google-shipping-setting';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-radio-field';

const repositoryMockFactory = () => {
    return {
        get: (id) => {
            const currencies = [
                {
                    id: 1,
                    isoCode: 'EUR'
                },
                {
                    id: 2,
                    isoCode: 'USD'
                }
            ];

            return Promise.resolve(currencies.filter((currency) => {
                return currency.id === id;
            }));
        }
    };
};

describe('module/sw-sales-channel/component/sw-sales-channel-google-shipping-setting', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Service().register('repositoryFactory', () => {
            return {
                create: () => repositoryMockFactory()
            };
        });

        Shopware.Service().register('googleShoppingService', () => {
            const errorResponse = {
                response: {
                    data: {
                        errors: [
                            {
                                code: 'This is an error code',
                                detail: 'This is an detailed error message'
                            }
                        ]
                    }
                }
            };

            return {
                setupShipping: (salesChannelId, flatRate) => {
                    if (salesChannelId && flatRate) {
                        return Promise.resolve();
                    }

                    // eslint-disable-next-line prefer-promise-reject-errors
                    return Promise.reject(errorResponse);
                }
            };
        });
    });

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-sales-channel-google-shipping-setting'), {
            stubs: {
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-radio-field': Shopware.Component.build('sw-radio-field'),
                'sw-number-field': '<div id="number-field"><slot name="suffix"></slot></div>',
                'sw-sales-channel-detail-protect-link': true,
                'sw-field-error': true
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $route: { name: 'sw.sales.channel.detail.base.step-6' },
                $router: { push: () => {} }
            },
            propsData: {
                salesChannel: {
                    id: 1,
                    productExports: [{ currencyId: '1' }]
                }
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should render number of item which equals to settingOptions length', async () => {
        wrapper.setData({
            settingOptions: [
                { value: 1, name: 'option 1' },
                { value: 2, name: 'option 2' },
                { value: 3, name: 'option 3' },
                { value: 4, name: 'option 4' }
            ]
        });

        await wrapper.vm.$nextTick();

        const radioFieldItems = wrapper.findAll('.sw-field__radio-option');
        expect(radioFieldItems.length).toEqual(wrapper.vm.settingOptions.length);
    });

    it('should show currency iso code in number field correctly', async () => {
        const response = await Shopware.Service('repositoryFactory').create().get(1);

        wrapper.setData({ currency: response[0] });

        await wrapper.vm.$nextTick();

        const numberField = wrapper.find('#number-field');
        expect(numberField.text()).toBe('EUR');
    });

    it('showErrorNotification should not be called update when onClickNext is successful', async () => {
        wrapper.setData({
            settingOptions: [
                { value: 'flatRate', name: 'option 1' },
                { value: 'selfSetup', name: 'option 2' }
            ],
            selectedSettingOption: 'flatRate',
            flatRate: 10
        });

        wrapper.setMethods({ createNotificationError: jest.fn() });

        const spy = jest.spyOn(wrapper.vm, 'showErrorNotification');

        await wrapper.vm.$nextTick();
        await wrapper.vm.onClickNext();

        expect(spy).not.toHaveBeenCalled();
    });

    it('showErrorNotification should be called update when onClickNext is failed', async () => {
        wrapper.setProps({
            salesChannel: {
                ...wrapper.vm.salesChannel,
                id: ''
            }
        });

        wrapper.setData({
            settingOptions: [
                { value: 'flatRate', name: 'option 1' },
                { value: 'selfSetup', name: 'option 2' }
            ]
        });

        wrapper.setMethods({ createNotificationError: jest.fn() });

        const spy = jest.spyOn(wrapper.vm, 'showErrorNotification');

        await wrapper.vm.$nextTick();
        await wrapper.vm.onClickNext();

        expect(spy).toHaveBeenCalled();
    });
});
