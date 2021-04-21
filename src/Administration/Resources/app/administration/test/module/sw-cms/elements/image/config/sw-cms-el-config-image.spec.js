/* eslint-disable max-len */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/image/config';
import 'src/module/sw-cms/component/sw-cms-mapping-field';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-cms-el-config-image'), {
        provide: {
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {};
                },
                getCmsElementRegistry: () => {
                    return { image: {} };
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
            'sw-field': true,
            'sw-select-field': {
                template: '<select class="sw-select-field" :value="value" @change="$emit(\'change\', $event.target.value)"><slot></slot></select>',
                props: ['value', 'options']
            },
            'sw-text-field': true,
            'sw-cms-mapping-field': Shopware.Component.build('sw-cms-mapping-field'),
            'sw-media-upload-v2': true,
            'sw-upload-listener': true
        },
        propsData: {
            element: {
                config: {
                    media: {
                        source: 'static',
                        value: null,
                        required: true,
                        entity: {
                            name: 'media'
                        }
                    },
                    displayMode: {
                        source: 'static',
                        value: 'standard'
                    },
                    url: {
                        source: 'static',
                        value: null
                    },
                    newTab: {
                        source: 'static',
                        value: false
                    },
                    minHeight: {
                        source: 'static',
                        value: '340px'
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

describe('src/module/sw-cms/elements/image/config', () => {
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
        const displayModeSelect = wrapper.find('.sw-cms-el-config-image__display-mode');

        displayModeSelect.setValue('cover');

        expect(wrapper.vm.element.config.minHeight.value).toBe('340px');

        displayModeSelect.setValue('standard');

        // Should still have the previous value
        expect(wrapper.vm.element.config.minHeight.value).toBe('340px');
    });
});
