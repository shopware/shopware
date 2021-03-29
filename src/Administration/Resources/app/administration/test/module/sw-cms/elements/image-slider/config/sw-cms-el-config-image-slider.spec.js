/* eslint-disable max-len */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/image-slider/config';
import 'src/module/sw-cms/component/sw-cms-mapping-field';

function createWrapper(activeTab = 'content') {
    return shallowMount(Shopware.Component.build('sw-cms-el-config-image-slider'), {
        mocks: {
            $tc: v => v
        },
        provide: {
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {};
                },
                getCmsElementRegistry: () => {
                    return { 'image-slider': {} };
                }
            },
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve()
                    };
                }
            }
        },
        stubs: {
            'sw-tabs': {
                props: ['defaultItem'],
                data() {
                    return { active: activeTab };
                },
                template: '<div><slot></slot><slot name="content" v-bind="{ active }"></slot></div>'
            },
            'sw-tabs-item': true,
            'sw-select-field': {
                template: '<select class="sw-select-field" :value="value" @change="$emit(\'change\', $event.target.value)"><slot></slot></select>',
                props: ['value', 'options']
            },
            'sw-container': true,
            'sw-field': true,
            'sw-text-field': true,
            'sw-cms-mapping-field': Shopware.Component.build('sw-cms-mapping-field'),
            'sw-media-list-selection-v2': true
        },
        propsData: {
            element: {
                config: {
                    sliderItems: {
                        source: 'static',
                        value: [],
                        required: true,
                        entity: {
                            name: 'media'
                        }
                    },
                    navigationArrows: {
                        source: 'static',
                        value: 'outside'
                    },
                    navigationDots: {
                        source: 'static',
                        value: null
                    },
                    displayMode: {
                        source: 'static',
                        value: 'standard'
                    },
                    minHeight: {
                        source: 'static',
                        value: '300px'
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null
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

describe('src/module/sw-cms/elements/image-slider/config', () => {
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

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should keep minHeight value when changing display mode', () => {
        const wrapper = createWrapper('settings');
        const displayModeSelect = wrapper.find('.sw-cms-el-config-image-slider__setting-display-mode');

        displayModeSelect.setValue('cover');

        expect(wrapper.vm.element.config.minHeight.value).toBe('300px');

        displayModeSelect.setValue('standard');

        // Should still have the previous value
        expect(wrapper.vm.element.config.minHeight.value).toBe('300px');
    });
});
