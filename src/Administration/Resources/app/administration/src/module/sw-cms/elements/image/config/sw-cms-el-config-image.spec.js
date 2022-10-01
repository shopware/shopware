/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import swCmsElConfigImage from 'src/module/sw-cms/elements/image/config';
import swCmsMappingField from 'src/module/sw-cms/component/sw-cms-mapping-field';

Shopware.Component.register('sw-cms-el-config-image', swCmsElConfigImage);
Shopware.Component.register('sw-cms-mapping-field', swCmsMappingField);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-cms-el-config-image'), {
        provide: {
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {};
                },
                getCmsElementRegistry: () => {
                    return { image: {} };
                },
            },
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve(),
                    };
                },
            },
        },
        stubs: {
            'sw-field': true,
            'sw-select-field': {
                template: '<select class="sw-select-field" :value="value" @change="$emit(\'change\', $event.target.value)"><slot></slot></select>',
                props: ['value', 'options'],
            },
            'sw-text-field': true,
            'sw-cms-mapping-field': await Shopware.Component.build('sw-cms-mapping-field'),
            'sw-media-upload-v2': true,
            'sw-upload-listener': true,
            'sw-dynamic-url-field': true,
        },
        propsData: {
            element: {
                config: {
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
            };
        },
    });
}

describe('src/module/sw-cms/elements/image/config', () => {
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
        const displayModeSelect = wrapper.find('.sw-cms-el-config-image__display-mode');

        await displayModeSelect.setValue('cover');

        expect(wrapper.vm.element.config.minHeight.value).toBe('340px');

        await displayModeSelect.setValue('standard');

        // Should still have the previous value
        expect(wrapper.vm.element.config.minHeight.value).toBe('340px');
    });
});
