import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/page/sw-extension-my-extensions-listing';
import ShopwareService from 'src/module/sw-extension/service/shopware-extension.service';

const shopwareService = new ShopwareService({}, {}, {}, {});
shopwareService.updateExtensionData = jest.fn();

function createWrapper() {
    const localVue = createLocalVue();
    localVue.filter('asset', key => key);

    return shallowMount(Shopware.Component.build('sw-extension-my-extensions-listing'), {
        localVue,
        propsData: {
        },
        mocks: {
            $tc: v => v,
            $route: { name: 'sw.extension.my-extensions.listing.app' }
        },
        stubs: {
            'router-link': true,
            'sw-self-maintained-extension-card': true,
            'sw-button': true,
            'sw-meteor-card': true
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
        expect(wrapper.vm.extensionList.length).toBe(1);
    });

    it('extensionList default has a no themes', () => {
        wrapper.vm.$route.name = 'sw.extension.my-extensions.listing.theme';

        expect(wrapper.vm.extensionList.length).toBe(0);
    });

    it('extensionList withThemes has a themes', () => {
        wrapper.vm.$route.name = 'sw.extension.my-extensions.listing.theme';

        Shopware.State.commit('shopwareExtensions/setExtensions', [{
            name: 'Test',
            installedAt: 'some date',
            isTheme: true
        }]);

        expect(wrapper.vm.extensionList.length).toBe(1);
    });
});
