import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-user-card';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/_action/country/formatting-address',
    status: 200,
    response: {
        data: 'random-address',
    }
});

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-order-user-card'), {
        propsData: {
            billingAddress: {
                country: {
                    useDefaultAddressFormat: false,
                    advancedAddressFormatPlain: 'random-format',
                },
            },
            versionContext: {},
            isLoading: true,
            isEditing: true,
            currentOrder: {
                currency: {
                    translated: {
                        shortName: ''
                    }
                },
                salesChannel: {
                    name: 'Channel Number 1',
                    translated: {
                        name: 'Channel Number 1'
                    }
                },
                language: {
                    name: '',
                },
                transactions: [],
                addresses: [{
                    street: 'Denesik Bridge',
                    zipcode: '05132',
                    city: 'Bernierstad',
                    id: '38e8895864a649a1b2ec806dad02ab87'
                }],
                deliveries: [],
                billingAddressId: '38e8895864a649a1b2ec806dad02ab87',
                orderCustomer: {
                    firstName: 'Duy',
                    lastName: 'Dinh',
                    email: 'duy@dinh.dev'
                },
            },
        },
        stubs: {
            'sw-container': true,
            'sw-address': true,
            'sw-card': true,
            'sw-button': true,
            'sw-avatar': true,
            'sw-order-inline-field': true,
            'sw-entity-tag-select': true,
            'sw-description-list': true,
            'sw-card-section': true,
        },
        mocks: {
            $route: {
                meta: {
                    $module: {
                        icon: 'default-object-plug',
                        title: 'sw.example.title',
                        color: '#189EFF'
                    }
                }
            }
        },
        provide: {
            orderService: {},
            countryAddressService: {
                formattingAddress() {
                    return Promise.resolve('random-address');
                }
            },
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve([]);
                    }
                }),
            },
        }
    });
}

describe('module/sw-order/component/sw-order-user-card', () => {
    let wrapper;

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render formatting address for billing address', async () => {
        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('sw-address-stub').attributes()['formatting-address']).toBe('random-address');
    });
});
