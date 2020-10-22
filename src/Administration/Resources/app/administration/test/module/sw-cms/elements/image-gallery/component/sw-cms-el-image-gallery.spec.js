import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/image-gallery/component';

const sliderItemsConfigMock = [
    {
        mediaId: '1',
        mediaUrl: 'http://shopware.com/image1.jpg'
    },
    {
        mediaId: '2',
        mediaUrl: 'http://shopware.com/image2.jpg'
    }
];

const sliderItemsDataMock = [
    {
        media: {
            id: '1',
            url: 'http://shopware.com/image1.jpg'
        }
    },
    {
        media: {
            id: '2',
            url: 'http://shopware.com/image2.jpg'
        }
    }
];

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-cms-el-image-gallery'), {
        localVue,
        sync: false,
        mocks: {
            $tc: v => v
        },
        provide: {
            feature: {
                isActive: () => true
            },
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {};
                },
                getCmsElementRegistry: () => {
                    return { 'image-gallery': {} };
                }
            }
        },
        stubs: {
            'sw-cms-el-image-slider': true,
            'sw-media-list-selection-item-v2': true,
            'sw-icon': true
        },
        propsData: {
            element: {
                config: {},
                data: {}
            },
            defaultConfig: {
                sliderItems: {
                    source: 'static',
                    value: []
                },
                galleryPosition: {
                    source: 'static',
                    value: 'left'
                },
                verticalAlign: {
                    source: 'static',
                    value: null
                }
            }
        },
        data() {
            return {
                cmsPageState: {
                    currentPage: {
                        type: 'ladingpage'
                    }
                }
            };
        }
    });
}

describe('src/module/sw-cms/elements/image-gallery/component', () => {
    it('should gallery empty if there is no slider items value', () => {
        const wrapper = createWrapper();

        const imagePlaceHolders = wrapper.findAll('.sw-cms-el-image-gallery__item-placeholder');
        const mediaSelection = wrapper.findAll('sw-media-list-selection-item-v2-stub');

        expect(imagePlaceHolders.exists()).toBeTruthy();
        expect(imagePlaceHolders.length).toBe(3);

        expect(mediaSelection.exists()).toBeFalsy();
    });

    it('should media items if there are slider items value', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    sliderItems: {
                        source: 'static',
                        value: sliderItemsConfigMock
                    },
                    ...wrapper.props().element.config
                },
                data: {
                    sliderItems: sliderItemsDataMock
                }
            }
        });

        const imagePlaceHolders = wrapper.findAll('.sw-cms-el-image-gallery__item-placeholder');
        const mediaSelection = wrapper.findAll('sw-media-list-selection-item-v2-stub');

        expect(imagePlaceHolders.exists()).toBeFalsy();

        expect(mediaSelection.exists()).toBeTruthy();
        expect(mediaSelection.length).toBe(2);
    });
});
