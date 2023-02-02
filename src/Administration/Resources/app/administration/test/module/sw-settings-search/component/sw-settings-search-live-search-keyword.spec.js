import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-search/component/sw-settings-search-live-search-keyword';

const defaultHighlightClass = '.sw-settings-search-live-search-keyword__highlight';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-settings-search-live-search-keyword'), {
        localVue,

        propsData: {
            text: '',
            searchTerm: '',
            highlightClass: 'sw-settings-search-live-search-keyword__highlight'
        }
    });
}

describe('src/module/sw-settings-search/component/sw-settings-search-live-search', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render no highlight keyword', async () => {
        await wrapper.setProps({
            searchTerm: 'made',
            text: 'Rustic Granite ShopVN'
        });

        const highlightItem = wrapper.find(defaultHighlightClass);
        expect(highlightItem.exists()).toBeFalsy();
    });

    it('should render 1 highlight keyword', async () => {
        await wrapper.setProps({
            searchTerm: 'iron',
            text: 'Durable Iron OpenDoor'
        });

        const highlightItem = wrapper.find(defaultHighlightClass);
        expect(highlightItem.exists()).toBeTruthy();
    });

    it('should render 1 highlight keyword with custom class', async () => {
        await wrapper.setProps({
            searchTerm: 'iron',
            text: 'Durable Iron OpenDoor',
            highlightClass: 'foo-blue-keyword'
        });

        const highlightItem = wrapper.find('.foo-blue-keyword');
        expect(highlightItem.exists()).toBeTruthy();
    });

    it('should render 3 keyword highlight', async () => {
        await wrapper.setProps({
            searchTerm: 'awesome wo qlear',
            text: 'Awesome Wooden Crystal Qlear'
        });

        const highlightItems = wrapper.findAll(defaultHighlightClass);
        expect((highlightItems.length)).toBe(wrapper.vm.searchTerm.split(' ').length);
    });
});
