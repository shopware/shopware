/**
 * @package buyers-experience
*/
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

const MOCK_ASSET_PATH = '/ASSET-PATH/';
Shopware.Context.api.assetsPath = MOCK_ASSET_PATH;

const sliderItemsConfigMock = [
    {
        mediaId: '1',
        mediaUrl: 'http://shopware.com/image1.jpg',
    },
    {
        mediaId: '2',
        mediaUrl: 'http://shopware.com/image2.jpg',
    },
    {
        mediaId: '3',
        mediaUrl: 'http://shopware.com/image3.jpg',
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
    {
        media: {
            id: '3',
            url: 'http://shopware.com/image3.jpg',
        },
    },
];

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-image-slider', {
        sync: true,
    }), {
        global: {
            sync: false,
            provide: {
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return {};
                    },
                    getCmsElementRegistry: () => {
                        return { 'image-slider': {} };
                    },
                },
            },
            stubs: {
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
                navigationArrows: {
                    source: 'static',
                    value: 'outside',
                },
                navigationDots: {
                    source: 'static',
                    value: null,
                },
                displayMode: {
                    source: 'static',
                    value: 'standard',
                },
                verticalAlign: {
                    source: 'static',
                    value: null,
                },
            },
        },
    });
}

describe('src/module/sw-cms/elements/image-slider/component', () => {
    beforeAll(() => {
        Shopware.Store.register({
            id: 'cmsPageState',
        });
    });

    it('should render a fallback image if config is not resolved to data', async () => {
        const wrapper = await createWrapper();

        const image = wrapper.get('.sw-cms-el-image-slider__image');

        expect(image.attributes('src')).toBe(`${MOCK_ASSET_PATH}administration/static/img/cms/preview_mountain_large.jpg`);
    });

    it('setSliderArrowItem should work correctly', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    ...wrapper.props().element.config,
                    sliderItems: {
                        source: 'static',
                        value: sliderItemsConfigMock,
                    },
                },
                data: {
                    sliderItems: sliderItemsDataMock,
                },
            },
        });

        // Initial state
        expect(wrapper.vm.sliderPos).toBe(0);
        expect(wrapper.vm.imgSrc).toBe('http://shopware.com/image1.jpg');

        // Click on back arrow
        wrapper.vm.setSliderArrowItem(-1);

        // Navigate to last item provided that first item is active
        expect(wrapper.vm.sliderPos).toBe(2);
        expect(wrapper.vm.imgSrc).toBe('http://shopware.com/image3.jpg');

        // Click on next arrow
        wrapper.vm.setSliderArrowItem(1);

        // Navigate to first item provided that last item is active
        expect(wrapper.vm.sliderPos).toBe(0);
        expect(wrapper.vm.imgSrc).toBe('http://shopware.com/image1.jpg');

        // Click on next arrow
        wrapper.vm.setSliderArrowItem(1);

        // Navigate to next item
        expect(wrapper.vm.sliderPos).toBe(1);
        expect(wrapper.vm.imgSrc).toBe('http://shopware.com/image2.jpg');
    });

    it('should render number of navigation dots correctly', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    ...wrapper.props().element.config,
                    sliderItems: {
                        source: 'static',
                        value: sliderItemsConfigMock,
                    },
                    navigationDots: {
                        source: 'static',
                        value: 'outside',
                    },
                },
                data: {
                    sliderItems: sliderItemsDataMock,
                },
            },
        });

        const navigationDots = wrapper.find('.sw-cms-el-image-slider__navigation-dots');
        expect(navigationDots.exists()).toBeTruthy();

        const navigationButtons = navigationDots.findAll('.sw-cms-el-image-slider__navigation-button');
        expect(navigationButtons).toHaveLength(sliderItemsConfigMock.length);
        expect(navigationButtons.at(0).classes()).toContain('is--active');
    });

    it('should render active image correctly after clicking on dot button', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    ...wrapper.props().element.config,
                    sliderItems: {
                        source: 'static',
                        value: sliderItemsConfigMock,
                    },
                    navigationDots: {
                        source: 'static',
                        value: 'outside',
                    },
                },
                data: {
                    sliderItems: sliderItemsDataMock,
                },
            },
        });

        wrapper.vm.setSliderItem(sliderItemsDataMock[1].media, 1);
        await wrapper.vm.$nextTick();

        const navigationButtons = wrapper.findAll('.sw-cms-el-image-slider__navigation-button');
        expect(navigationButtons.at(1).classes()).toContain('is--active');
        expect(wrapper.vm.sliderPos).toBe(1);
    });
});
