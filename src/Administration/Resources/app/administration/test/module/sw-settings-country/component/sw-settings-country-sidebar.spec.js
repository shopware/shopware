import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-country/component/sw-settings-country-sidebar';
import { ADDRESS_VARIABLES } from 'src/module/sw-settings-country/constant/address.constant';

const customerData = {
    defaultBillingAddress: {
        company: 'shopware-AG',
        department: 'Development',
        street: 'Ebbinghoff 10',
        city: 'Schöppingen',
        zipcode: '48624',
        phoneNumber: '123456789',
        country: {
            translated: {
                name: 'Germany'
            }
        },
        countryState: {
            translated: {
                name: 'North Rhine-Westphalia'
            }
        },
        salutation: {
            translated: {
                displayName: 'Ms.',
            }
        },
        additionalAddressLine1: '',
        additionalAddressLine2: '',
    },
    title: '',
    firstName: 'Quynh',
    lastName: 'Nguyen',
    id: '1234'
};

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-country-sidebar'), {
        localVue,

        mocks: {
            $tc: key => key,
            $route: {
                params: {
                    id: 'id'
                }
            },
            $device: {
                getSystemKey: () => {},
                onResize: () => {}
            }
        },

        propsData: {
            country: {},
            isLoading: false
        },

        provide: {
            repositoryFactory: {
                create: () => ({
                    get: () => {
                        return Promise.resolve({});
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
        },

        stubs: {
            'sw-sidebar': {
                template: '<div><slot></slot></div>'
            },
            'sw-sidebar-item': {
                template: '<div><slot></slot></div>'
            },
            'sw-button': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>'
            },
            'sw-icon': true,
            'sw-entity-single-select': {
                model: {
                    prop: 'value',
                    event: 'change',
                },
                props: ['value'],
                template: `
                        <input
                           :value="value"
                           @input="onChange"
                           class="sw-entity-single-select" />
                      `,
                methods: {
                    onChange(event) {
                        this.$emit('change', event.target.value, customerData);
                    }
                }
            },
        }
    });
}

describe('module/sw-settings-country/component/sw-settings-country-sidebar', () => {
    it('should all variables on variables', async () => {
        const wrapper = createWrapper();
        const variableItems = wrapper.findAll('.sw-settings-country-sidebar__variable-item');

        variableItems.wrappers.forEach((item, index) => {
            expect(item.text()).toEqual(ADDRESS_VARIABLES[index]);
        });
    });

    it('should show customer info correctly', async () => {
        const wrapper = createWrapper();
        const customerSelect = wrapper.find('.sw-settings-country-sidebar__customer-select');

        await customerSelect.setValue('1234');
        await customerSelect.trigger('input');

        const previewData = {
            company: 'shopware-AG',
            department: 'Development',
            title: '',
            firstName: 'Quynh',
            lastName: 'Nguyen',
            street: 'Ebbinghoff 10',
            city: 'Schöppingen',
            country: 'Germany',
            countryState: 'North Rhine-Westphalia',
            salutation: 'Ms.',
            phoneNumber: '123456789',
            zipcode: '48624',
            additionalAddressLine1: '',
            additionalAddressLine2: '',
        };

        const variableProperty = wrapper.findAll('.sw-settings-country-sidebar__customer-property');
        const variableValue = wrapper.findAll('.sw-settings-country-sidebar__customer-value');

        Object.keys(previewData).forEach((key, index) => {
            expect(variableProperty.wrappers[index].text()).toEqual(key);
            expect(variableValue.wrappers[index].text()).toEqual(previewData[key]);
        });
    });

    it('should emit open-preview-modal event when click on button Preview', async () => {
        const wrapper = createWrapper();
        const customerSelect = wrapper.find('.sw-settings-country-sidebar__customer-select');

        await customerSelect.setValue('1234');
        await customerSelect.trigger('input');

        const buttonPreview = wrapper.find('.sw-settings-country-sidebar__preview-button');
        expect(buttonPreview.exists()).toBeTruthy();

        await buttonPreview.trigger('click');

        expect(JSON.stringify(wrapper.emitted()['open-preview-modal'][0][0]))
            .toEqual(JSON.stringify(customerData));
    });
});
