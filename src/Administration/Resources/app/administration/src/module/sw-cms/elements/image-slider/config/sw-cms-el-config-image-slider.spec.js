/**
 * @package buyers-experience
 */
/* eslint-disable max-len */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

async function createWrapper(activeTab = 'content', sliderItems = []) {
    return mount(await wrapTestComponent('sw-cms-el-config-image-slider', {
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
                        return { 'image-slider': {} };
                    },
                },
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve({
                                get: (mediaId) => {
                                    /* if media is not found, return null, otherwise return a valid mediaItem */
                                    return (mediaId === 'deletedId') ? null : {
                                        id: '0',
                                        position: 0,
                                    };
                                },
                            }),
                        };
                    },
                },
                mediaService: {},
            },
            stubs: {
                'sw-tabs': {
                    props: ['defaultItem'],
                    data() {
                        return { active: activeTab };
                    },
                    template: '<div><slot></slot><slot name="content" v-bind="{ active }"></slot></div>',
                },
                'sw-tabs-item': true,
                'sw-select-field': {
                    template: '<select class="sw-select-field" :value="value" @change="$emit(\'change\', $event.target.value)"><slot></slot></select>',
                    props: ['value', 'options'],
                },
                'sw-container': true,
                'sw-field': true,
                'sw-text-field': true,
                'sw-number-field': true,
                'sw-cms-mapping-field': await wrapTestComponent('sw-cms-mapping-field'),
                'sw-media-list-selection-v2': await wrapTestComponent('sw-media-list-selection-v2'),
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-help-text': true,
                'sw-field-error': true,
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
                        value: sliderItems,
                        required: true,
                        entity: {
                            name: 'media',
                        },
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
                    minHeight: {
                        source: 'static',
                        value: '300px',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                    autoSlide: {
                        source: 'static',
                        value: false,
                    },
                    speed: {
                        source: 'static',
                        value: 300,
                    },
                    autoplayTimeout: {
                        source: 'static',
                        value: 5000,
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
                    {
                        id: 'deletedId',
                        position: 4,
                    },
                ],
            };
        },
    });
}

describe('src/module/sw-cms/elements/image-slider/config', () => {
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

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should keep minHeight value when changing display mode', async () => {
        const wrapper = await createWrapper('settings');
        const displayModeSelect = wrapper.find('.sw-cms-el-config-image-slider__setting-display-mode');

        await displayModeSelect.setValue('cover');

        expect(wrapper.vm.element.config.minHeight.value).toBe('300px');

        await displayModeSelect.setValue('standard');

        // Should still have the previous value
        expect(wrapper.vm.element.config.minHeight.value).toBe('300px');
    });

    it('should be able to show auto slide switch', async () => {
        const wrapper = await createWrapper('settings');
        const autoSlideOption = wrapper.find('.sw-cms-el-config-image-slider__setting-auto-slide');
        expect(autoSlideOption.exists()).toBeTruthy();
    });

    it('should disable delay element and speed element when auto slide switch is falsy', async () => {
        const wrapper = await createWrapper('settings');
        const delaySlide = wrapper.find('.sw-cms-el-config-image-slider__setting-delay-slide');
        const speedSlide = wrapper.find('.sw-cms-el-config-image-slider__setting-speed-slide');
        expect(delaySlide.attributes().disabled).toBe('true');
        expect(speedSlide.attributes().disabled).toBe('true');
    });

    it('should not disable delay element and speed element when auto slide switch is truthy', async () => {
        const wrapper = await createWrapper('settings');
        await flushPromises();

        const delaySlide = wrapper.find('.sw-cms-el-config-image-slider__setting-delay-slide');
        const speedSlide = wrapper.find('.sw-cms-el-config-image-slider__setting-speed-slide');
        const autoSlideOption = wrapper.find('.sw-cms-el-config-image-slider__setting-auto-slide input');
        await autoSlideOption.setChecked();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showSlideConfig).toBe(true);
        expect(delaySlide.attributes().disabled).toBeUndefined();
        expect(speedSlide.attributes().disabled).toBeUndefined();
    });

    it('should sort the item list on drag and drop', async () => {
        const wrapper = await createWrapper('content');
        await flushPromises();

        const mediaListSelectionV2Vm = wrapper.findComponent('.sw-media-list-selection-v2').vm;
        mediaListSelectionV2Vm.$emit('item-sort', mediaListSelectionV2Vm.mediaItems[1], mediaListSelectionV2Vm.mediaItems[2], true);
        await wrapper.vm.$nextTick();

        const items = wrapper.findAll('.sw-media-item');

        expect(items).toHaveLength(5);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('2');
        expect(items.at(2).text()).toBe('1');
        expect(items.at(3).text()).toBe('3');
        expect(items.at(4).text()).toBe('deletedId');
    });

    it('should remove deleted media from imageSlider', async () => {
        const sliderItems = [
            { filename: 'a.jpg', mediaId: 'a' },
            { filename: 'b.jpg', mediaId: 'b' },
            { filename: 'c.jpg', mediaId: 'c' },
            { filename: 'd.jpg', mediaId: 'd' },
            { filename: 'notfound.jpg', mediaId: 'deletedId' },
        ];

        const wrapper = await createWrapper('content', sliderItems);
        await flushPromises();
        const validItems = wrapper.findAll('.sw-media-item');

        expect(sliderItems).toHaveLength(5);
        expect(validItems).toHaveLength(4);
    });
});
