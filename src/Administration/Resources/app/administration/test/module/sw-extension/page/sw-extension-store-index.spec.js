import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/page/sw-extension-store-index';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-extension-store-index'), {
        localVue,
        propsData: {},
        mocks: {
            $tc: v => v
        },
        stubs: {
            'sw-meteor-page': {
                template: `
<div class="sw-meteor-page">
    <slot name="search-bar"></slot>
</div>
                `
            },
            'sw-search-bar': {
                template: '<div class="sw-search-bar"></div>'
            }
        },
        provide: {}
    });
}

const setSearchValueMock = jest.fn();
describe('src/module/sw-extension/page/sw-extension-store-index', () => {
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

    it('should commit the search value to the store', async () => {
        expect(setSearchValueMock).not.toHaveBeenCalled();

        const searchBar = wrapper.find('.sw-search-bar');
        await searchBar.vm.$emit('search', 'Nice theme');

        expect(setSearchValueMock).toHaveBeenCalledWith({}, {
            key: 'term',
            value: 'Nice theme'
        });
    });
});
