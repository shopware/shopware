/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import swCmsElImageGallery from 'src/module/sw-cms/elements/image-gallery/component';

Shopware.Component.register('sw-cms-el-image-gallery', swCmsElImageGallery);

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

async function createWrapper(propsOverride, dataOverride) {
    return shallowMount(await Shopware.Component.build('sw-cms-el-image-gallery'), {
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
        propsData: {
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
        data() {
            return {
                cmsPageState: {
                    currentPage: {
                        type: 'ladingpage',
                    },
                },
                ...dataOverride,
            };
        },
    });
}

describe('src/module/sw-cms/elements/image-gallery/component', () => {
    it('should map to product media if the component is in a product page', async () => {
        const wrapper = await createWrapper(null, {
            cmsPageState: {
                currentPage: {
                    type: 'product_detail',
                },
            },
        });

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

        expect(imagePlaceHolders.exists()).toBeTruthy();
        expect(imagePlaceHolders).toHaveLength(3);

        expect(mediaSelection.exists()).toBeFalsy();
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

        const imagePlaceHolders = wrapper.findAll('.sw-cms-el-image-gallery__item-placeholder');
        const mediaSelection = wrapper.findAll('sw-media-list-selection-item-v2-stub');

        expect(imagePlaceHolders.exists()).toBeFalsy();

        expect(mediaSelection.exists()).toBeTruthy();
        expect(mediaSelection).toHaveLength(2);
    });
});
