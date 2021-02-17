import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/page/sw-extension-store-detail';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-extension-store-detail'), {
        localVue,
        propsData: {
            id: 'a1b2c3'
        },
        mocks: {
            $tc: v => v,
            $route: {
                hash: ''
            }
        },
        stubs: {
            'sw-meteor-page': {
                template: '<div></div>'
            },
            'sw-search-bar': {
                template: '<div class="sw-search-bar"></div>'
            },
            'sw-extensions-store-label-display': true
        },
        provide: {
            shopwareExtensionService: {},
            extensionStoreDataService: {}
        }
    });
}

const setSearchValueMock = jest.fn();
describe('src/module/sw-extension/page/sw-extension-store-detail', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(async () => (
        Shopware.State.registerModule('shopwareExtensions', {
            namespaced: true,
            mutations: {
                setSearchValue: setSearchValueMock
            }
        })
    ));

    beforeEach(async () => {
        wrapper = await createWrapper();
        setSearchValueMock.mockClear();
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show all extension category names', async () => {
        await wrapper.setData({
            extension: {
                categories: [
                    { details: { name: 'Productivity' } },
                    { details: { name: 'Admin' } },
                    { details: { name: 'Storefront' } }
                ]
            }
        });

        expect(wrapper.vm.extensionCategoryNames).toBe('Productivity, Admin, Storefront');
    });
});
