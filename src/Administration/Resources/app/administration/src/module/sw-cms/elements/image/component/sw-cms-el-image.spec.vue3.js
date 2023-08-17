/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils_v3';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

const mediaDataMock = {
    id: '1',
    url: 'http://shopware.com/image1.jpg',
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-image', {
        sync: true,
    }), {
        global: {
            provide: {
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return {};
                    },
                    getCmsElementRegistry: () => {
                        return { image: {} };
                    },
                    getPropertyByMappingPath: () => {
                        return {};
                    },
                },
            },
        },
        props: {
            element: {
                config: {},
                data: {},
            },
            defaultConfig: {
                media: {
                    source: 'static',
                    value: null,
                    required: true,
                    entity: {
                        name: 'media',
                    },
                },
                displayMode: {
                    source: 'static',
                    value: 'standard',
                },
                url: {
                    source: 'static',
                    value: null,
                },
                newTab: {
                    source: 'static',
                    value: false,
                },
                minHeight: {
                    source: 'static',
                    value: '340px',
                },
                verticalAlign: {
                    source: 'static',
                    value: null,
                },
                horizontalAlign: {
                    source: 'static',
                    value: null,
                },
            },
        },
        data() {
            return {
                cmsPageState: {
                    currentPage: {
                        type: 'ladingpage',
                    },
                },
            };
        },
    });
}

describe('src/module/sw-cms/elements/image/component', () => {
    it('should show default image if there is no config value', async () => {
        const wrapper = await createWrapper();

        const img = wrapper.find('img');
        expect(img.attributes('src'))
            .toBe(wrapper.vm.assetFilter('administration/static/img/cms/preview_mountain_large.jpg'));
    });

    it('should show media source regarding to media data', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    ...wrapper.props().element.config,
                    media: {
                        source: 'static',
                        value: '1',
                    },
                },
                data: {
                    media: mediaDataMock,
                },
            },
        });

        const img = wrapper.find('img');
        expect(img.attributes('src')).toContain(mediaDataMock.url);
    });

    it('should show default image if demo value is undefined', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    ...wrapper.props().element.config,
                    media: {
                        source: 'mapped',
                        value: 'category.media',
                    },
                },
                data: mediaDataMock,
            },
        });

        const img = wrapper.find('img');
        expect(img.attributes('src'))
            .toBe(wrapper.vm.assetFilter('administration/static/img/cms/preview_mountain_large.jpg'));
    });
});
