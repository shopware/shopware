import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-user-card';
import 'src/app/component/base/sw-address';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-order-user-card'), {
        propsData: {
            billingAddress: {
                company: 'Shopware - AG',
                country: {
                    addressFormat: [[{ type: 'snippet', value: 'address/company' }]],
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
                    id: '38e8895864a649a1b2ec806dad02ab87',
                    company: 'Shopware - AG',
                    country: {
                        addressFormat: [[{ type: 'snippet', value: 'address/company' }]],
                    },
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
            'sw-address': Shopware.Component.build('sw-address'),
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
            customSnippetApiService: {
                render() {
                    return Promise.resolve({
                        rendered: 'Christa Stracke<br/> \\n \\n Philip Inlet<br/> \\n \\n \\n \\n 22005-3637 New Marilyneside<br/> \\n \\n Moldova (Republic of)<br/><br/>'
                    });
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
        global.activeFeatureFlags = ['v6.5.0.0'];

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-address .sw-address__formatting').text()).toBe('Christa Stracke \\n \\n Philip Inlet \\n \\n \\n \\n 22005-3637 New Marilyneside \\n \\n Moldova (Republic of)');
    });
});
