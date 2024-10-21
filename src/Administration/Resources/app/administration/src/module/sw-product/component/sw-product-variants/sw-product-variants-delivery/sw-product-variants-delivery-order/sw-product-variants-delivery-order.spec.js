/**
 * @package inventory
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-product-variants-delivery-order', {
            sync: true,
        }),
        {
            props: {
                product: {
                    configuratorSettings: [],
                    variantListingConfig: {
                        displayParent: null,
                        configuratorGroupConfig: [],
                        mainVariantId: null,
                    },
                },
                selectedGroups: [
                    {
                        name: 'textile',
                        description: null,
                        displayType: 'text',
                        sortingType: 'alphanumeric',
                        filterable: true,
                        visibleOnProductDetailPage: true,
                        position: 1,
                        customFields: null,
                        translated: {
                            name: 'TranslatedTextile',
                            description: null,
                            position: 1,
                            customFields: [],
                        },
                        apiAlias: null,
                        id: '0ccea31f2d774b06bb6459c64cd334ce',
                    },
                    {
                        name: 'color',
                        description: null,
                        displayType: 'text',
                        sortingType: 'alphanumeric',
                        filterable: true,
                        visibleOnProductDetailPage: true,
                        position: 1,
                        customFields: null,
                        translated: {
                            name: 'TranslatedColor',
                            description: null,
                            position: 1,
                            customFields: [],
                        },
                        apiAlias: null,
                        id: 'e6cea31f2d774b06ab6459c64cd3345h',
                    },
                ],
            },
            global: {
                provide: {
                    repositoryFactory: {},
                    mediaService: {},
                },
                stubs: {
                    'sw-loader': true,
                    'sw-tree': true,
                    'sw-tree-item': true,
                },
            },
        },
    );
}

// eslint-disable-next-line max-len
describe('src/module/sw-product/component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-order', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the translated name of a property group', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const textileGroup = wrapper.vm.orderObjects.find((group) => {
            return group.id === '0ccea31f2d774b06bb6459c64cd334ce';
        });

        expect(textileGroup.name).toBe('TranslatedTextile');
    });
});
