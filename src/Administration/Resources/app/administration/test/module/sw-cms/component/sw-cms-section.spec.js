import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import 'src/module/sw-cms/component/sw-cms-section';
import Vuex from 'vuex';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-cms-section'), {
        localVue,
        propsData: {
            page: {},
            section: {
                type: 'sidebar',
                blocks: [
                    {
                        id: '1a2b',
                        sectionPosition: 'main',
                        type: 'foo-bar'
                    },
                    {
                        id: '3cd4',
                        sectionPosition: 'sidebar',
                        type: 'foo-bar'
                    },
                    {
                        id: '5ef6',
                        sectionPosition: 'sidebar',
                        type: 'foo-bar-removed'
                    },
                    {
                        id: '7gh8',
                        sectionPosition: 'main',
                        type: 'foo-bar-removed'
                    }
                ]
            }
        },
        stubs: {
            'sw-icon': true,
            'sw-cms-section-actions': true,
            'sw-cms-block': true,
            'sw-cms-block-foo-bar': true,
            'sw-cms-stage-add-block': true
        },
        provide: {
            repositoryFactory: {},
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {
                        'foo-bar': {}
                    };
                }
            }
        }
    });
}
describe('module/sw-cms/component/sw-cms-section', () => {
    beforeAll(() => {
        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            state: {
                selectedBlock: {
                    id: '1a2b',
                    sectionPosition: 'main',
                    type: 'foo-bar'
                },
                isSystemDefaultLanguage: true
            }
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('the disable all sub components', async () => {
        const wrapper = createWrapper();

        const cmsSectionActions = wrapper.find('sw-cms-section-actions-stub');
        expect(cmsSectionActions.attributes().disabled).toBeFalsy();

        const cmsBlock = wrapper.find('sw-cms-block-stub');
        expect(cmsBlock.attributes().disabled).toBeFalsy();

        const cmsStageAddBlocks = wrapper.findAll('sw-cms-stage-add-block-stub');
        expect(cmsStageAddBlocks.length).toBe(4);

        cmsStageAddBlocks.wrappers.forEach(cmsStageAddBlock => {
            expect(cmsStageAddBlock.exists()).toBeTruthy();
        });
    });

    it('the disable all sub components', async () => {
        const wrapper = createWrapper();
        await wrapper.setProps({
            disabled: true
        });

        const cmsSectionActions = wrapper.find('sw-cms-section-actions-stub');
        expect(cmsSectionActions.attributes().disabled).toBe('true');

        const cmsBlock = wrapper.find('sw-cms-block-stub');
        expect(cmsBlock.attributes().disabled).toBe('true');

        const cmsStageAddBlocks = wrapper.findAll('sw-cms-stage-add-block-stub');
        expect(cmsStageAddBlocks.length).toBe(0);

        cmsStageAddBlocks.wrappers.forEach(cmsStageAddBlock => {
            expect(cmsStageAddBlock.exists()).toBeFalsy();
        });
    });
});
