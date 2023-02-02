/* eslint-disable max-len */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/image-slider/config';
import 'src/module/sw-cms/component/sw-cms-mapping-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-base-field';

function createWrapper(activeTab = 'content') {
    return shallowMount(Shopware.Component.build('sw-cms-el-config-image-slider'), {
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
            'sw-number-field': true,
            'sw-cms-mapping-field': Shopware.Component.build('sw-cms-mapping-field'),
            'sw-media-list-selection-v2': true,
            'sw-switch-field': Shopware.Component.build('sw-switch-field'),
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-help-text': true,
            'sw-field-error': true,
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
                    },
                    autoSlide: {
                        source: 'static',
                        value: false
                    },
                    speed: {
                        source: 'static',
                        value: 300
                    },
                    autoplayTimeout: {
                        source: 'static',
                        value: 5000
                    },
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

    it('should be able to show auto slide switch', () => {
        const wrapper = createWrapper('settings');
        const autoSlideOption = wrapper.find('.sw-cms-el-config-image-slider__setting-auto-slide');
        expect(autoSlideOption.exists()).toBeTruthy();
    });

    it('should be disable delay element and speed element when auto slide switch is falsy', () => {
        const wrapper = createWrapper('settings');
        const delaySlide = wrapper.find('.sw-cms-el-config-image-slider__setting-delay-slide');
        const speedSlide = wrapper.find('.sw-cms-el-config-image-slider__setting-speed-slide');
        expect(delaySlide.attributes().disabled).toBe('true');
        expect(speedSlide.attributes().disabled).toBe('true');
    });

    it('should be not disable delay element and speed element when auto slide switch is truthy', async () => {
        const wrapper = createWrapper('settings');
        const delaySlide = wrapper.find('.sw-cms-el-config-image-slider__setting-delay-slide');
        const speedSlide = wrapper.find('.sw-cms-el-config-image-slider__setting-speed-slide');
        const autoSlideOption = wrapper.find('.sw-cms-el-config-image-slider__setting-auto-slide input');
        await autoSlideOption.setChecked();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showSlideConfig).toBe(true);
        expect(delaySlide.attributes().disabled).toBe(undefined);
        expect(speedSlide.attributes().disabled).toBe(undefined);
    });
});
