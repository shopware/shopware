/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import swCmsVisibilityToggle from 'src/module/sw-cms/component/sw-cms-visibility-toggle';
import swCmsSection from 'src/module/sw-cms/component/sw-cms-section';

Shopware.Component.register('sw-cms-visibility-toggle', swCmsVisibilityToggle);
Shopware.Component.register('sw-cms-section', swCmsSection);

async function createWrapper() {
    if (typeof Shopware.State.get('cmsPageState') !== 'undefined') {
        Shopware.State.unregisterModule('cmsPageState');
    }

    Shopware.State.registerModule('cmsPageState', {
        namespaced: true,
        state: {
            selectedBlock: {
                id: '1a2b',
                sectionPosition: 'main',
                type: 'foo-bar'
            },
            isSystemDefaultLanguage: true,
            currentCmsDeviceView: 'desktop',
        }
    });

    return shallowMount(await Shopware.Component.build('sw-cms-section'), {
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
            'sw-cms-stage-add-block': true,
            'sw-cms-visibility-toggle': await Shopware.Component.build('sw-cms-visibility-toggle'),
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
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('the disable all sub components', async () => {
        const wrapper = await createWrapper();

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
        const wrapper = await createWrapper();
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

    it('the visibility toggle wrapper should exist and be visible', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            section: {
                ...wrapper.props().section,
                visibility: {
                    mobile: true,
                    tablet: true,
                    desktop: false,
                }
            }
        });

        expect(wrapper.find('.sw-cms-visibility-toggle-wrapper').exists()).toBeTruthy();
    });

    it('should be able to collapsed or expanded', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            section: {
                ...wrapper.props().section,
                visibility: {
                    mobile: true,
                    tablet: true,
                    desktop: false,
                }
            }
        });

        expect(wrapper.find('.sw-cms-visibility-toggle-wrapper').classes()).not.toContain('is--expanded');
        await wrapper.find('.sw-cms-visibility-toggle__button').trigger('click');
        expect(wrapper.find('.sw-cms-visibility-toggle-wrapper').classes()).toContain('is--expanded');
    });

    it('the visibility toggle wrapper should not exist', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-visibility-toggle-wrapper').exists()).toBeFalsy();
    });

    it('the `visibility` property should not be empty', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.props().section.visibility).toStrictEqual({ desktop: true, mobile: true, tablet: true });
    });
});
