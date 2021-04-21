import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/image-gallery/config';
import 'src/module/sw-cms/component/sw-cms-mapping-field';

const mediaDataMock = [
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

function createWrapper(activeTab = 'content') {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-cms-el-config-image-gallery'), {
        localVue,
        sync: false,
        provide: {
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {};
                },
                getCmsElementRegistry: () => {
                    return { 'image-gallery': {} };
                }
            },
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve(mediaDataMock)
                    };
                }
            }
        },
        stubs: {
            'sw-tabs': {
                data() {
                    return { active: activeTab };
                },
                template: '<div><slot></slot><slot name="content" v-bind="{ active }"></slot></div>'
            },
            'sw-tabs-item': true,
            'sw-container': { template: '<div class="sw-container"><slot></slot></div>' },
            'sw-media-modal-v2': true,
            'sw-media-list-selection-v2': true,
            'sw-field': true,
            'sw-switch-field': true,
            'sw-select-field': {
                // eslint-disable-next-line max-len
                template: '<select class="sw-select-field" :value="value" @change="$emit(\'change\', $event.target.value)"><slot></slot></select>',
                props: ['value', 'options']
            },
            'sw-text-field': true,
            'sw-alert': true,
            'sw-cms-mapping-field': Shopware.Component.build('sw-cms-mapping-field')
        },
        propsData: {
            element: {
                config: {
                    sliderItems: {
                        source: 'static',
                        value: []
                    },
                    navigationArrows: {
                        source: 'static',
                        value: 'inside'
                    },
                    navigationDots: {
                        source: 'static',
                        value: null
                    },
                    galleryPosition: {
                        source: 'static',
                        value: 'left'
                    },
                    displayMode: {
                        source: 'static',
                        value: 'standard'
                    },
                    minHeight: {
                        source: 'static',
                        value: '340px'
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null
                    },
                    zoom: {
                        source: 'static',
                        value: false
                    },
                    fullScreen: {
                        source: 'static',
                        value: false
                    }
                },
                data: {}
            },
            defaultConfig: {}
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

describe('src/module/sw-cms/elements/image-gallery/config', () => {
    beforeAll(() => {
        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            state: {
                currentMappingTypes: {},
                currentDemoEntity: null
            },
            mutations: {
                setCurrentDemoEntity(state, entity) {
                    state.currentDemoEntity = entity;
                }
            }
        });
    });

    it('should media selection if sliderItems config source is static', async () => {
        const wrapper = createWrapper();

        const mediaList = wrapper.find('sw-media-list-selection-v2-stub');
        const mappingValue = wrapper.find('.sw-cms-mapping-field__mapping-value');
        const mappingPreview = wrapper.find('.sw-cms-mapping-field__preview');

        expect(mediaList.exists()).toBeTruthy();
        expect(mappingValue.exists()).toBeFalsy();
        expect(mappingPreview.exists()).toBeFalsy();
    });

    it('should mapping value and preview mapping if sliderItems config source is mapped', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    sliderItems: {
                        source: 'mapped',
                        value: 'product.media'
                    }
                },
                data: {}
            }
        });

        const mediaList = wrapper.find('sw-media-list-selection-v2-stub');
        const mappingValue = wrapper.find('.sw-cms-mapping-field__mapping-value');
        const mappingPreview = wrapper.find('.sw-cms-mapping-field__preview');

        expect(mediaList.exists()).toBeFalsy();
        expect(mappingValue.exists()).toBeTruthy();
        expect(mappingPreview.exists()).toBeTruthy();
    });

    it('should keep minHeight value when changing display mode', () => {
        const wrapper = createWrapper('settings');
        const displayModeSelect = wrapper.find('.sw-cms-el-config-image-gallery__setting-display-mode');

        displayModeSelect.setValue('cover');

        expect(wrapper.vm.element.config.minHeight.value).toBe('340px');

        displayModeSelect.setValue('standard');

        // Should still have the previous value
        expect(wrapper.vm.element.config.minHeight.value).toBe('340px');
    });
});
