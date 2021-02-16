import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/page/sw-extension-my-extensions-listing';
import 'src/app/component/grid/sw-pagination';
import 'src/app/component/base/sw-button';
import ShopwareService from 'src/module/sw-extension/service/shopware-extension.service';

const shopwareService = new ShopwareService({}, {}, {}, {});
shopwareService.updateExtensionData = jest.fn();
const routerReplaceMock = jest.fn();

function createWrapper() {
    const localVue = createLocalVue();
    localVue.filter('asset', key => key);

    return shallowMount(Shopware.Component.build('sw-extension-my-extensions-listing'), {
        localVue,
        mocks: {
            $tc: v => v,
            $route: {
                name: 'sw.extension.my-extensions.listing.app',
                query: {}
            },
            $router: {
                replace: routerReplaceMock
            }
        },
        stubs: {
            'router-link': true,
            'sw-self-maintained-extension-card': {
                template: '<div class="sw-self-maintained-extension-card"></div>',
                props: ['extension']
            },
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-meteor-card': true,
            'sw-pagination': Shopware.Component.build('sw-pagination'),
            'sw-icon': true,
            'sw-field': true
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {};
                }
            },
            shopwareExtensionService: shopwareService
        }
    });
}

describe('src/module/sw-extension/page/sw-extension-my-extensions-listing', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('shopwareExtensions', {
            namespaced: true,
            state: {
                myExtensions: {
                    data: [
                        {
                            name: 'Test',
                            installedAt: null
                        }
                    ]
                }
            },
            mutations: {
                setExtensions(state, extensions) {
                    state.myExtensions.data = extensions;
                }
            }
        });
    });

    beforeEach(async () => {
        Shopware.State.commit('shopwareExtensions/setExtensions', [
            {
                name: 'Test',
                installedAt: null
            }
        ]);

        routerReplaceMock.mockClear();
        wrapper = await createWrapper();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('openStore should call router', () => {
        wrapper.vm.$router = {
            push: jest.fn()
        };

        wrapper.vm.openStore();

        expect(wrapper.vm.$router.push).toBeCalled();
    });

    it('updateList should call update extensions', () => {
        wrapper.vm.updateList();

        expect(shopwareService.updateExtensionData).toBeCalled();
    });

    it('extensionList default has a app', () => {
        const extensionCards = wrapper.findAll('.sw-self-maintained-extension-card');

        expect(extensionCards.length).toBe(1);
    });

    it('extensionList default has a no themes', async () => {
        wrapper.vm.$route.name = 'sw.extension.my-extensions.listing.theme';

        await wrapper.vm.$nextTick();

        const extensionCards = wrapper.findAll('.sw-self-maintained-extension-card');

        expect(extensionCards.length).toBe(0);
    });

    it('extensionList withThemes has a themes', async () => {
        wrapper.vm.$route.name = 'sw.extension.my-extensions.listing.theme';

        Shopware.State.commit('shopwareExtensions/setExtensions', [{
            name: 'Test',
            installedAt: 'some date',
            isTheme: true
        }]);

        await wrapper.vm.$nextTick();

        const extensionCards = wrapper.findAll('.sw-self-maintained-extension-card');

        expect(extensionCards.length).toBe(1);
    });

    it('should update the route with the default values', async () => {
        expect(routerReplaceMock).toHaveBeenCalledWith({
            name: 'sw.extension.my-extensions.listing.app',
            params: undefined,
            query: {
                limit: 25,
                page: 1,
                term: undefined
            }
        });
    });

    it('should update the route with the new values from pagination', async () => {
        // load 40 extensions
        const extensions = Array(40).fill().map((_, i) => {
            return { name: `extension card number ${i}`, installedAt: `foo-${i}` };
        });

        Shopware.State.commit('shopwareExtensions/setExtensions', extensions);

        await wrapper.vm.$nextTick();

        // reset mock
        routerReplaceMock.mockClear();
        expect(routerReplaceMock).not.toHaveBeenCalled();

        // check if only shows first 25 extensions
        let extensionCards = wrapper.findAll('.sw-self-maintained-extension-card');
        expect(extensionCards.length).toBe(25);
        expect(extensionCards.at(0).props('extension').name).toEqual('extension card number 0');

        // go to second page
        const nextButton = wrapper.find('.sw-pagination__page-button-next');
        await nextButton.trigger('click');

        // check if router goes to second page
        expect(routerReplaceMock.mock.calls[0][0].query.page).toEqual(2);

        // simulate change in url
        wrapper.vm.$route.query = { page: 2 };
        await wrapper.vm.$nextTick();

        // check if it shows now only 15 extensions
        extensionCards = wrapper.findAll('.sw-self-maintained-extension-card');
        expect(extensionCards.length).toBe(15);
        expect(extensionCards.at(0).props('extension').name).toEqual('extension card number 25');
    });

    it('should search the extensions', async () => {
        // load 60 extensions
        const extensions = Array(40).fill().map((_, i) => {
            return { name: `extension card number ${i}`, installedAt: `foo-${i}` };
        });

        Shopware.State.commit('shopwareExtensions/setExtensions', extensions);

        await wrapper.vm.$nextTick();

        // check if only shows first 25 extensions
        let extensionCards = wrapper.findAll('.sw-self-maintained-extension-card');
        expect(extensionCards.length).toBe(25);
        expect(extensionCards.at(0).props('extension').name).toEqual('extension card number 0');

        // enter search value
        wrapper.vm.$route.query = { term: 'number 1' };
        await wrapper.vm.$nextTick();

        // check if it shows now only 11 extensions
        extensionCards = wrapper.findAll('.sw-self-maintained-extension-card');
        expect(extensionCards.length).toBe(11);

        // check some random entries
        expect(extensionCards.at(0).props('extension').name).toEqual('extension card number 1');
        expect(extensionCards.at(1).props('extension').name).toEqual('extension card number 10');
        expect(extensionCards.at(10).props('extension').name).toEqual('extension card number 19');
    });

    [
        {
            key: 'page',
            value: 2
        },
        {
            key: 'limit',
            value: 50
        },
        {
            key: 'term',
            value: 'number 1'
        }
    ].forEach(({ key, value }) => {
        it(`should update ${key} in route when it gets changed in the pagination`, async () => {
            // load 60 extensions
            const extensions = Array(60).fill().map((_, i) => {
                return { name: `extension card number ${i}`, installedAt: `foo-${i}` };
            });

            Shopware.State.commit('shopwareExtensions/setExtensions', extensions);

            await wrapper.vm.$nextTick();

            // reset mock
            routerReplaceMock.mockClear();
            expect(routerReplaceMock).not.toHaveBeenCalled();

            // change computed value
            wrapper.vm[key] = value;

            // check if route gets update
            expect(routerReplaceMock.mock.calls[0][0].query[key]).toEqual(value);
        });
    });
});
