/**
 * @sw-package discovery
 */

import { flushPromises, mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const mediaDataMock = {
    id: '1',
    url: 'http://shopware.com/video1.mp4',
};

const coverMediaMock = {
    id: 'cover-1',
    url: 'http://shopware.com/video1-cover.jpg',
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-video', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                    repositoryFactory: {
                        create: () => ({
                            get: (id) => Promise.resolve(id === coverMediaMock.id ? coverMediaMock : mediaDataMock),
                        }),
                    },
                },
            },
            props: {
                element: {
                    type: 'video',
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
                    autoPlay: {
                        source: 'static',
                        value: false,
                    },
                    muted: {
                        source: 'static',
                        value: true,
                    },
                    loop: {
                        source: 'static',
                        value: false,
                    },
                    playsInline: {
                        source: 'static',
                        value: false,
                    },
                    showControls: {
                        source: 'static',
                        value: true,
                    },
                    showCover: {
                        source: 'static',
                        value: false,
                    },
                },
            },
        },
    );
}

describe('src/module/sw-cms/elements/video/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/video');
    });

    it('should render without a source when there is no media data', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('video').exists()).toBe(false);
        expect(wrapper.find('.sw-cms-el-video__content-placeholder').exists()).toBe(true);
    });

    it('should show media source regarding to media data', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                type: 'video',
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

        const video = wrapper.find('video');
        expect(video.attributes('src')).toContain(mediaDataMock.url);
    });

    it('should show mapped demo media when demo value is available', async () => {
        Shopware.Store.get('cmsPage').setCurrentDemoEntity({
            media: {
                url: 'http://shopware.com/demo-video.mp4',
            },
        });

        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                type: 'video',
                config: {
                    ...wrapper.props().element.config,
                    media: {
                        source: 'mapped',
                        value: 'category.media',
                    },
                },
                data: {},
            },
        });

        const video = wrapper.find('video');
        expect(video.attributes('src')).toContain('http://shopware.com/demo-video.mp4');
    });

    it('should resolve mapped media ids from custom fields', async () => {
        Shopware.Store.get('cmsPage').setCurrentDemoEntity({
            customFields: {
                heroVideo: '1',
            },
        });

        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                type: 'video',
                config: {
                    ...wrapper.props().element.config,
                    media: {
                        source: 'mapped',
                        value: 'category.customFields.heroVideo',
                    },
                },
                data: {},
            },
        });

        await flushPromises();

        const video = wrapper.find('video');
        expect(video.attributes('src')).toContain(mediaDataMock.url);
    });

    it('should show cover poster when cover media is present', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                type: 'video',
                config: {
                    ...wrapper.props().element.config,
                    showCover: {
                        source: 'static',
                        value: true,
                    },
                },
                data: {
                    media: {
                        ...mediaDataMock,
                        extensions: {
                            videoCoverMedia: coverMediaMock,
                        },
                    },
                },
            },
        });

        const video = wrapper.find('video');
        expect(video.attributes('poster')).toBe(coverMediaMock.url);
    });
});
