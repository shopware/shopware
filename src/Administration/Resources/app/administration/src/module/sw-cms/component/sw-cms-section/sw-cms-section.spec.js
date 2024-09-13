/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-section', {
        sync: true,
    }), {
        props: {
            page: {},
            section: {
                visibility: {
                    mobile: true,
                    tablet: true,
                    desktop: true,
                },
                type: 'sidebar',
                blocks: [
                    {
                        id: '1a2b',
                        sectionPosition: 'main',
                        type: 'foo-bar',
                    },
                    {
                        id: '3cd4',
                        sectionPosition: 'sidebar',
                        type: 'foo-bar',
                    },
                    {
                        id: '5ef6',
                        sectionPosition: 'sidebar',
                        type: 'foo-bar-removed',
                    },
                    {
                        id: '7gh8',
                        sectionPosition: 'main',
                        type: 'foo-bar-removed',
                    },
                ],
            },
        },
        global: {
            stubs: {
                'sw-icon': true,
                'sw-cms-section-actions': true,
                'sw-cms-block': {
                    template: '<div class="sw-cms-block"><slot /></div>',
                },
                'sw-cms-stage-add-block': {
                    template: '<div class="sw-cms-stage-add-block"><slot /></div>',
                },
                'sw-cms-visibility-toggle': await wrapTestComponent('sw-cms-visibility-toggle'),
                'sw-cms-block-foo-bar': {
                    props: ['block'],
                    template: '<div class="sw-cms-block-foo-bar"></div>',
                },
                'sw-cms-slot': true,
            },
            provide: {
                repositoryFactory: {},
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return {
                            'foo-bar': {},
                        };
                    },
                },
            },
        },
    });
}

describe('module/sw-cms/component/sw-cms-section', () => {
    beforeEach(() => {
        Shopware.Store.unregister('cmsPageState');

        Shopware.Store.register({
            id: 'cmsPageState',
            state: () => ({
                selectedBlock: {
                    id: '1a2b',
                    sectionPosition: 'main',
                    type: 'foo-bar',
                },
                isSystemDefaultLanguage: true,
                currentCmsDeviceView: 'desktop',
            }),
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not disable all sub components', async () => {
        const wrapper = await createWrapper();

        const cmsSectionActions = wrapper.find('sw-cms-section-actions-stub');
        expect(cmsSectionActions.attributes().disabled).toBeFalsy();

        const cmsBlock = wrapper.find('.sw-cms-block');
        expect(cmsBlock.attributes().disabled).toBeFalsy();

        const cmsStageAddBlocks = wrapper.findAll('.sw-cms-stage-add-block');
        expect(cmsStageAddBlocks).toHaveLength(4);

        cmsStageAddBlocks.forEach(cmsStageAddBlock => {
            expect(cmsStageAddBlock.exists()).toBeTruthy();
        });
    });

    it('should disable all sub components', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true,
        });

        const cmsSectionActions = wrapper.find('sw-cms-section-actions-stub');
        expect(cmsSectionActions.attributes().disabled).toBe('true');

        const cmsBlock = wrapper.find('.sw-cms-block');
        expect(cmsBlock.attributes().disabled).toBe('true');

        const cmsStageAddBlocks = wrapper.findAll('sw-cms-stage-add-block-stub');
        expect(cmsStageAddBlocks).toHaveLength(0);

        cmsStageAddBlocks.forEach(cmsStageAddBlock => {
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
                },
            },
        });

        await flushPromises();

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
                },
            },
        });

        await flushPromises();

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

    it('should have the block value as attribute in the rendered blocks', async () => {
        const wrapper = await createWrapper();

        // Get first CMS stage block
        const fooBarBlock = wrapper.findComponent('.sw-cms-section__content .sw-cms-block-foo-bar');
        expect(fooBarBlock.props()).toEqual({
            block: {
                id: '1a2b',
                sectionPosition: 'main',
                type: 'foo-bar',
            },
        });
    });
});
