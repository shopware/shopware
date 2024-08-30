/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

const sliderItemsConfigMock = [
    {
        mediaId: '1',
        mediaUrl: 'http://shopware.com/image1.jpg',
    },
    {
        mediaId: '2',
        mediaUrl: 'http://shopware.com/image2.jpg',
    },
];

const sliderItemsDataMock = [
    {
        media: {
            id: '1',
            url: 'http://shopware.com/image1.jpg',
        },
    },
    {
        media: {
            id: '2',
            url: 'http://shopware.com/image2.jpg',
        },
    },
];

async function createWrapper(propsOverride) {
    return mount(await wrapTestComponent('sw-cms-el-image-gallery', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            provide: {
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return {};
                    },
                    getCmsElementRegistry: () => {
                        return { 'image-gallery': {} };
                    },
                    getPropertyByMappingPath: () => {
                        return {};
                    },
                },
            },
            stubs: {
                'sw-cms-el-image-slider': true,
                'sw-media-list-selection-item-v2': true,
                'sw-icon': true,
            },
        },
        props: {
            element: {
                config: {},
                data: {},
            },
            defaultConfig: {
                sliderItems: {
                    source: 'static',
                    value: [],
                },
                galleryPosition: {
                    source: 'static',
                    value: 'left',
                },
                verticalAlign: {
                    source: 'static',
                    value: null,
                },
                displayMode: {
                    source: 'static',
                    value: 'standard',
                },
                minHeight: {
                    source: 'static',
                    value: '340px',
                },
                zoom: {
                    source: 'static',
                    value: false,
                },
                fullScreen: {
                    source: 'static',
                    value: false,
                },
                navigationArrows: {
                    source: 'static',
                    value: 'inside',
                },
                navigationDots: {
                    source: 'static',

                },
            },
            ...propsOverride,
        },
    });
}

describe('src/module/sw-cms/elements/image-gallery/component', () => {
    beforeAll(() => {
        Shopware.Store.register({
            id: 'cmsPageState',

            state() {
                return {
                    currentPage: {
                        type: 'landingpage',
                    },
                    currentMappingEntity: null,
                    currentDemoEntity: null,
                    currentMappingTypes: {},
                };
            },
        });
    });

    beforeEach(() => {
        Shopware.Store.get('cmsPageState').$reset();
    });

    it('should map to product media if the component is in a product page', async () => {
        Shopware.Store.get('cmsPageState').currentPage.type = 'product_detail';
        const wrapper = await createWrapper(null);

        expect(wrapper.vm.element.config.sliderItems.source).toBe('mapped');
        expect(wrapper.vm.element.config.sliderItems.value).toBe('product.media');
    });

    it('should not initially map to product media if the component is sliderItems data exists', async () => {
        const wrapper = await createWrapper({
            element: {
                config: {
                    sliderItems: {
                        source: 'static',
                        value: sliderItemsConfigMock,
                    },
                },
                translated: {
                    config: {
                        sliderItems: {
                            source: 'static',
                            value: sliderItemsConfigMock,
                        },
                    },
                },
            },
        }, {
            cmsPageState: {
                currentPage: {
                    type: 'product_detail',
                },
            },
        });

        expect(wrapper.vm.element.config.sliderItems.source).toBe('static');
        expect(wrapper.vm.element.config.sliderItems.value).toEqual(sliderItemsConfigMock);
    });

    it('should gallery empty if there is no slider items value', async () => {
        const wrapper = await createWrapper();

        const imagePlaceHolders = wrapper.findAll('.sw-cms-el-image-gallery__item-placeholder');
        const mediaSelection = wrapper.findAll('sw-media-list-selection-item-v2-stub');

        expect(imagePlaceHolders).toHaveLength(3);
        expect(mediaSelection).toHaveLength(0);
    });

    it('should media items if there are slider items value', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    sliderItems: {
                        source: 'static',
                        value: sliderItemsConfigMock,
                    },
                    ...wrapper.props().element.config,
                },
                data: {
                    sliderItems: sliderItemsDataMock,
                },
            },
        });
        await flushPromises();

        const imagePlaceHolders = wrapper.findAll('.sw-cms-el-image-gallery__item-placeholder');
        const mediaSelection = wrapper.findAll('sw-media-list-selection-item-v2-stub');

        expect(imagePlaceHolders).toHaveLength(0);
        expect(mediaSelection).toHaveLength(2);
    });
});
