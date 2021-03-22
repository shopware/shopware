import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-media';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-product-variants-delivery-media'), {
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
        mocks: {
            $tc: () => {}
        },
        provide: {
            repositoryFactory: {},
            mediaService: {}
        },
        stubs: {
            'sw-icon': true
        }
    });
}

// eslint-disable-next-line max-len
describe('src/module/sw-product/component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-media', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the translated name of a property group', async () => {
        const wrapper = createWrapper();
        const groups = wrapper.findAll('.sw-product-variants-delivery-media__groupElement');

        const textileGroup = groups.wrappers.find(group => {
            return group.text() === 'TranslatedTextile';
        });

        expect(textileGroup).toBeTruthy();
    });
});
