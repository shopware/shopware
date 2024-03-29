/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

const mediaDataMock = [
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

async function createWrapper(activeTab = 'content') {
    return mount(await wrapTestComponent('sw-cms-el-config-image-gallery', {
        sync: true,
    }), {
        global: {
            provide: {
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return {};
                    },
                    getCmsElementRegistry: () => {
                        return { 'image-gallery': {} };
                    },
                },
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(mediaDataMock),
                        };
                    },
                },
                mediaService: {},
            },
            stubs: {
                'sw-tabs': {
                    data() {
                        return { active: activeTab };
                    },
                    template: '<div><slot></slot><slot name="content" v-bind="{ active }"></slot></div>',
                },
                'sw-tabs-item': true,
                'sw-container': { template: '<div class="sw-container"><slot></slot></div>' },
                'sw-media-modal-v2': true,
                'sw-media-list-selection-v2': await wrapTestComponent('sw-media-list-selection-v2'),
                'sw-field': true,
                'sw-switch-field': true,
                'sw-select-field': {
                    // eslint-disable-next-line max-len
                    template: '<select class="sw-select-field" :value="value" @change="$emit(\'change\', $event.target.value)"><slot></slot></select>',
                    props: ['value', 'options'],
                },
                'sw-text-field': true,
                'sw-alert': true,
                'sw-cms-mapping-field': await wrapTestComponent('sw-cms-mapping-field'),
                'sw-upload-listener': true,
                'sw-media-upload-v2': true,
                'sw-media-list-selection-item-v2': {
                    template: '<div class="sw-media-item">{{item.id}}</div>',
                    props: ['item'],
                },
            },
        },
        props: {
            element: {
                config: {
                    sliderItems: {
                        source: 'static',
                        value: [],
                    },
                    navigationArrows: {
                        source: 'static',
                        value: 'inside',
                    },
                    navigationDots: {
                        source: 'static',
                        value: null,
                    },
                    galleryPosition: {
                        source: 'static',
                        value: 'left',
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
                    zoom: {
                        source: 'static',
                        value: false,
                    },
                    fullScreen: {
                        source: 'static',
                        value: false,
                    },
                    keepAspectRatioOnZoom: {
                        source: 'static',
                        value: true,
                    },
                    magnifierOverGallery: {
                        source: 'static',
                        value: false,
                    },
                },
                data: {},
            },
            defaultConfig: {},
        },
        data() {
            return {
                cmsPageState: {
                    currentPage: {
                        type: 'ladingpage',
                    },
                },
                mediaItems: [
                    {
                        id: '0',
                        position: 0,
                    },
                    {
                        id: '1',
                        position: 1,
                    },
                    {
                        id: '2',
                        position: 2,
                    },
                    {
                        id: '3',
                        position: 3,
                    },
                ],
            };
        },
    });
}

describe('src/module/sw-cms/elements/image-gallery/config', () => {
    beforeAll(() => {
        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            state: {
                currentMappingTypes: {},
                currentDemoEntity: null,
            },
            mutations: {
                setCurrentDemoEntity(state, entity) {
                    state.currentDemoEntity = entity;
                },
            },
        });
    });

    it('should media selection if sliderItems config source is static', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const mediaList = wrapper.find('.sw-media-list-selection-v2');
        const mappingValue = wrapper.find('.sw-cms-mapping-field__mapping-value');
        const mappingPreview = wrapper.find('.sw-cms-mapping-field__preview');

        expect(mediaList.exists()).toBeTruthy();
        expect(mappingValue.exists()).toBeFalsy();
        expect(mappingPreview.exists()).toBeFalsy();
    });

    it('should mapping value and preview mapping if sliderItems config source is mapped', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            element: {
                config: {
                    sliderItems: {
                        source: 'mapped',
                        value: 'product.media',
                    },
                },
                data: {},
            },
        });

        const mediaList = wrapper.find('sw-media-list-selection-v2-stub');
        const mappingValue = wrapper.find('.sw-cms-mapping-field__mapping-value');
        const mappingPreview = wrapper.find('.sw-cms-mapping-field__preview');

        expect(mediaList.exists()).toBeFalsy();
        expect(mappingValue.exists()).toBeTruthy();
        expect(mappingPreview.exists()).toBeTruthy();
    });

    it('should keep minHeight value when changing display mode', async () => {
        const wrapper = await createWrapper('settings');
        const displayModeSelect = wrapper.find('.sw-cms-el-config-image-gallery__setting-display-mode');

        await displayModeSelect.setValue('cover');

        expect(wrapper.vm.element.config.minHeight.value).toBe('340px');

        await displayModeSelect.setValue('standard');

        // Should still have the previous value
        expect(wrapper.vm.element.config.minHeight.value).toBe('340px');
    });

    it('should sort the item list on drag and drop', async () => {
        const wrapper = await createWrapper('content');
        await flushPromises();

        const mediaListSelectionV2Vm = wrapper.findComponent('.sw-media-list-selection-v2').vm;
        mediaListSelectionV2Vm.$emit('item-sort', mediaListSelectionV2Vm.mediaItems[1], mediaListSelectionV2Vm.mediaItems[2], true);
        await wrapper.vm.$nextTick();

        const items = wrapper.findAll('.sw-media-item');

        expect(items).toHaveLength(4);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('2');
        expect(items.at(2).text()).toBe('1');
        expect(items.at(3).text()).toBe('3');
    });

    it('should remove previous mediaItem if it already exists after upload', async () => {
        const wrapper = await createWrapper('content');
        await flushPromises();

        // Check length of sliderItems values
        expect(wrapper.vm.element.config.sliderItems.value).toHaveLength(0);

        // Simulate the upload of the first media item
        wrapper.vm.onImageUpload(mediaDataMock[0].media);
        expect(wrapper.vm.element.config.sliderItems.value).toHaveLength(1);
        expect(wrapper.vm.element.config.sliderItems.value[0].mediaUrl).toBe('http://shopware.com/image1.jpg');

        // Simulate the upload of the same media item with different URL (replacement)
        wrapper.vm.onImageUpload({
            ...mediaDataMock[0].media,
            url: 'http://shopware.com/image1-updated.jpg',
        });

        // Should still only have one item and the URL should be updated
        expect(wrapper.vm.element.config.sliderItems.value).toHaveLength(1);
        expect(wrapper.vm.element.config.sliderItems.value[0].mediaUrl).toBe('http://shopware.com/image1-updated.jpg');
    });
});
