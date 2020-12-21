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

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-cms-el-config-image-gallery'), {
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
                props: ['defaultItem'],
                data() {
                    return { active: this.defaultItem || '' };
                },
                template: '<div><slot></slot><slot name="content" v-bind="{ active }"></slot></div>'
            },
            'sw-tabs-item': true,
            'sw-container': { template: '<div class="sw-container"><slot></slot></div>' },
            'sw-media-modal-v2': true,
            'sw-media-list-selection-v2': true,
            'sw-field': true,
            'sw-switch-field': true,
            'sw-select-field': true,
            'sw-alert': true,
            'sw-cms-mapping-field': Shopware.Component.build('sw-cms-mapping-field')
        },
        propsData: {
            element: {
                config: {
                    sliderItems: {
                        source: 'static',
                        value: []
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
});
