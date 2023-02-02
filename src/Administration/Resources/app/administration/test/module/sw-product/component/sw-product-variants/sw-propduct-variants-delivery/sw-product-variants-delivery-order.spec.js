import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-order';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-product-variants-delivery-order'), {
        localVue,
        propsData: {
            product: {
                configuratorSettings: []
            },
            selectedGroups: [{
                name: 'textile',
                description: null,
                displayType: 'text',
                sortingType: 'alphanumeric',
                filterable: true,
                visibleOnProductDetailPage: true,
                position: 1,
                customFields: null,
                translated: { name: 'TranslatedTextile', description: null, position: 1, customFields: [] },
                apiAlias: null,
                id: '0ccea31f2d774b06bb6459c64cd334ce'
            }, {
                name: 'color',
                description: null,
                displayType: 'text',
                sortingType: 'alphanumeric',
                filterable: true,
                visibleOnProductDetailPage: true,
                position: 1,
                customFields: null,
                translated: { name: 'TranslatedColor', description: null, position: 1, customFields: [] },
                apiAlias: null,
                id: 'e6cea31f2d774b06ab6459c64cd3345h'
            }]
        },
        provide: {
            repositoryFactory: {},
            mediaService: {}
        },
        stubs: {
            'sw-loader': true,
            'sw-tree': true
        }
    });
}

// eslint-disable-next-line max-len
describe('src/module/sw-product/component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-order', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the translated name of a property group', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const textileGroup = wrapper.vm.orderObjects.find(group => {
            return group.id === '0ccea31f2d774b06bb6459c64cd334ce';
        });

        expect(textileGroup.name).toBe('TranslatedTextile');
    });
});
