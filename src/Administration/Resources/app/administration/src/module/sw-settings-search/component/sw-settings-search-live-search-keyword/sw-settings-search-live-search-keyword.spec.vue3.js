/**
 * @package system-settings
 */
import { mount } from '@vue/test-utils_v3';

const defaultHighlightClass = '.sw-settings-search-live-search-keyword__highlight';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-search-live-search-keyword', {
        sync: true,
    }), {
        props: {
            text: '',
            searchTerm: '',
            highlightClass: 'sw-settings-search-live-search-keyword__highlight',
        },
    });
}

describe('src/module/sw-settings-search/component/sw-settings-search-live-search', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render no highlight keyword', async () => {
        await wrapper.setProps({
            searchTerm: 'made',
            text: 'Rustic Granite ShopVN',
        });

        const highlightItem = wrapper.find(defaultHighlightClass);
        expect(highlightItem.exists()).toBeFalsy();
    });

    it('should render 1 highlight keyword', async () => {
        await wrapper.setProps({
            searchTerm: 'iron',
            text: 'Durable Iron OpenDoor',
        });

        const highlightItem = wrapper.find(defaultHighlightClass);
        expect(highlightItem.exists()).toBeTruthy();
    });

    it('should render 1 highlight keyword with custom class', async () => {
        await wrapper.setProps({
            searchTerm: 'iron',
            text: 'Durable Iron OpenDoor',
            highlightClass: 'foo-blue-keyword',
        });

        const highlightItem = wrapper.find('.foo-blue-keyword');
        expect(highlightItem.exists()).toBeTruthy();
    });

    it('should render 3 keyword highlight', async () => {
        await wrapper.setProps({
            searchTerm: 'awesome wo qlear',
            text: 'Awesome Wooden Crystal Qlear',
        });

        const highlightItems = wrapper.findAll(defaultHighlightClass);
        expect((highlightItems)).toHaveLength(wrapper.vm.searchTerm.split(' ').length);
    });
});
