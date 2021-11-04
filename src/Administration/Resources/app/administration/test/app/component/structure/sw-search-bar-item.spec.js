import { shallowMount } from '@vue/test-utils';
import 'src/app/component/structure/sw-search-bar-item';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-search-bar-item'), {
        propsData: {
            item: {
                name: null,
                id: '1001',
                parentId: '1000',
                variation: [
                    { group: 'color', option: 'red' },
                    { group: 'size', option: '39' }
                ],
                translated: { name: 'Product test' }
            },
            index: 1,
            type: '',
            column: 1,
            searchTerm: null,
            entityIconColor: '',
            entityIconName: ''
        }
    });
}

describe('src/app/component/structure/sw-search-bar-item', () => {
    let wrapper;

    it('should get correct name of variant products', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_6040'];

        wrapper = createWrapper();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.productDisplayName).toEqual('Product test (color: red | size: 39)');
    });
});
