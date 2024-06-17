/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';

const { EntityCollection, Entity } = Shopware.Data;

function getBlockData(position, id = '1a2b') {
    return {
        id,
        position,
        sectionPosition: 0,
        slots: [{
            id: 'some-slot-id',
        }],
    };
}

function getBlockCollection(blocks) {
    return new EntityCollection(blocks, 'blocks', null, null, blocks);
}

async function createWrapper({ cmsBlockRegistry } = { cmsBlockRegistry: null }) {
    localStorage.clear();

    Shopware.Store.unregister('cmsPageState');

    Shopware.Store.register({
        id: 'cmsPageState',
        state: () => ({
            isSystemDefaultLanguage: true,
            currentPageType: 'product_list',
            selectedBlock: {
                id: '1a2b',
                sectionPosition: 'main',
                type: 'foo-bar',
                slots: [],
                visibility: {
                    desktop: true,
                    tablet: true,
                    mobile: true,
                },
            },
            selectedSection: {
                id: '1111',
                blocks: [],
                visibility: {
                    desktop: true,
                    tablet: true,
                    mobile: true,
                },
            },
        }),
    });

    return mount(await wrapTestComponent('sw-cms-sidebar', {
        sync: true,
    }), {
        props: {
            page: {
                sections: [
                    new Entity('1111', 'section', {
                        type: 'sidebar',
                        blocks: getBlockCollection([
                            {
                                id: '1a2b',
                                sectionPosition: 'main',
                                type: 'foo-bar',
                                slots: [],
                            },
                            {
                                id: '3cd4',
                                sectionPosition: 'sidebar',
                                type: 'foo-bar',
                                slots: [],
                            },
                            {
                                id: '5ef6',
                                sectionPosition: 'sidebar',
                                type: 'foo-bar-removed',
                                slots: [],
                            },
                            {
                                id: '7gh8',
                                sectionPosition: 'main',
                                type: 'foo-bar-removed',
                                slots: [],
                            },
                        ]),
                    }),
                    new Entity('2222', 'section', {
                        type: 'sidebar',
                        blocks: getBlockCollection([{
                            id: 'abcd',
                            sectionPosition: 'main',
                            type: 'some-type',
                            slots: [],
                        }]),
                    }),
                ],
                type: 'product_list',
            },
        },
        global: {
            renderStubDefaultSlot: true,
            directives: {
                tooltip: {
                    beforeMount(el, binding) {
                        el.setAttribute('tooltip-message', binding.value.message);
                    },
                    mounted(el, binding) {
                        el.setAttribute('tooltip-message', binding.value.message);
                    },
                    updated(el, binding) {
                        el.setAttribute('tooltip-message', binding.value.message);
                    },
                },
            },
            stubs: {
                'sw-button': {
                    template: '<div class="sw-button" @click="$emit(`click`)"></div>',
                },
                'sw-sidebar': true,
                'sw-sidebar-item': {
                    template: '<div class="sw-sidebar-item"><slot #default /></div>',
                    props: ['disabled'],
                },
                'sw-sidebar-collapse': {
                    template: '<div class="sw-sidebar-collapse"><slot name="header" /><slot name="content" /></div>',
                },
                'sw-text-field': true,
                'sw-select-field': true,
                'sw-cms-block-config': true,
                'sw-cms-block-layout-config': true,
                'sw-cms-section-config': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-cms-sidebar-nav-element': true,
                'sw-entity-single-select': true,
                'sw-modal': true,
                'sw-checkbox-field': true,
                'sw-icon': true,
                'sw-cms-visibility-config': {
                    template: '<div class="sw-cms-visibility-config"></div>',
                    props: ['visibility'],
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            const mockCollection = [];
                            mockCollection.add = function add(e) { this.push(e); };
                            return {
                                id: null,
                                slots: mockCollection,
                            };
                        },
                        save: () => {},
                    }),
                },
                cmsBlockFavorites: {
                    isFavorite() {
                        return false;
                    },
                },
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return cmsBlockRegistry ?? {
                            image: {
                                name: 'image',
                                label: 'sw-cms.blocks.image.image.label',
                                category: 'image',
                                component: 'sw-cms-block-image',
                                previewComponent: 'sw-cms-preview-image',
                                defaultConfig: {
                                    marginBottom: '20px',
                                    marginTop: '20px',
                                    marginLeft: '20px',
                                    marginRight: '20px',
                                    sizingMode: 'boxed',
                                },
                                slots: {
                                    image: {
                                        type: 'image',
                                        default: {
                                            config: {
                                                displayMode: { source: 'static', value: 'standard' },
                                            },
                                            data: {
                                                media: { value: 'preview_mountain_large.jpg', source: 'default' },
                                            },
                                        },
                                    },
                                },
                            },
                        };
                    },
                    isBlockAllowedInPageType: (name, pageType) => name.startsWith(pageType),
                },
                cmsPageTypeService: {
                    getTypes: () => {
                        return [{
                            name: 'page',
                        }, {
                            name: 'landingpage',
                        }, {
                            name: 'product_list',
                        }, {
                            name: 'product_detail',
                        }];
                    },
                },
            },
        },
    });
}

describe('module/sw-cms/component/sw-cms-sidebar', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('disable all sidebar items', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true,
        });

        await flushPromises();

        const sidebarItems = wrapper.findAllComponents('.sw-sidebar-item');
        expect(sidebarItems).toHaveLength(5);

        sidebarItems.forEach(sidebarItem => {
            expect(sidebarItem.props('disabled')).toBe(true);
        });
    });

    it('enable all sidebar items', async () => {
        const wrapper = await createWrapper();

        const sidebarItems = wrapper.findAllComponents('.sw-sidebar-item');
        expect(sidebarItems).toHaveLength(5);

        sidebarItems.forEach(sidebarItem => {
            expect(sidebarItem.props('disabled')).toBe(false);
        });
    });

    it('should correctly adjust the sectionId when drag sorting', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        const blockDrag = {
            block: getBlockData(0, '1a2b'),
            sectionIndex: 0,
            position: 0,
        };
        const blockDrop = {
            block: getBlockData(3, '7gh8'),
            sectionIndex: 0,
        };

        wrapper.vm.onBlockDragSort(blockDrag, blockDrop, true);

        const sections = wrapper.vm.page.sections;
        expect(Array.from(sections[0].blocks.getIds())).toStrictEqual(['3cd4', '5ef6', '7gh8', '1a2b']);
        expect(Array.from(sections[1].blocks.getIds())).toStrictEqual(['abcd']);

        sections[0].blocks.forEach((block, index) => {
            expect(block.position).toBe(index);
        });
    });


    it('should correctly adjust the sectionId when drag sorting (cross section)', async () => {
        const wrapper = await createWrapper();

        const blockDrag = {
            block: getBlockData(0, '1a2b'),
            sectionIndex: 0,
        };
        const blockDrop = {
            block: getBlockData(2, '7gh8'),
            sectionIndex: 1,
        };

        wrapper.vm.onBlockDragSort(blockDrag, blockDrop, true);

        const sections = wrapper.vm.page.sections;
        expect(Array.from(sections[0].blocks.getIds())).toStrictEqual(['3cd4', '5ef6', '7gh8']);
        expect(Array.from(sections[1].blocks.getIds())).toStrictEqual(['abcd', '1a2b']);

        sections[0].blocks.forEach((block, index) => {
            expect(block.position).toBe(index);
        });

        sections[1].blocks.forEach((block, index) => {
            expect(block.position).toBe(index);
        });

        expect(Array.from(sections[0]._origin.blocks.getIds())).toStrictEqual(['3cd4', '5ef6', '7gh8']);

        expect(blockDrag.block.sectionId).toBe('2222');
    });

    it('should stop prompting a warning when entering the navigator, when "Do not remind me" option has been checked once', async () => {
        const wrapper = await createWrapper();

        // Check initial state of modal and localStorage
        expect(localStorage.getItem('cmsNavigatorDontRemind')).toBeFalsy();
        expect(wrapper.vm.showSidebarNavigatorModal).toBe(false);

        // Open the configuration modal
        wrapper.vm.$refs.blockNavigator.isActive = true;
        wrapper.vm.onSidebarNavigatorClick();
        expect(wrapper.vm.showSidebarNavigatorModal).toBe(true);

        // Check "don't remind me" and confirm the modal
        wrapper.vm.navigatorDontRemind = true;
        wrapper.vm.onSidebarNavigationConfirm();
        expect(localStorage.getItem('cmsNavigatorDontRemind')).toBe('true');
        expect(wrapper.vm.showSidebarNavigatorModal).toBe(false);

        // Close the sidebar
        wrapper.vm.$refs.blockNavigator.isActive = false;

        // Reopen the blockNavigator, to see that the modal won't be triggered

        wrapper.vm.$refs.blockNavigator.isActive = true;
        wrapper.vm.onSidebarNavigatorClick();
        expect(localStorage.getItem('cmsNavigatorDontRemind')).toBe('true');
        expect(wrapper.vm.showSidebarNavigatorModal).toBe(false);
    });

    it('should continue prompting a warning when entering the navigator, when "Do not remind me" option has not been checked once', async () => {
        const wrapper = await createWrapper();

        // Check initial state of modal and localStorage
        expect(localStorage.getItem('cmsNavigatorDontRemind')).toBeFalsy();
        expect(wrapper.vm.showSidebarNavigatorModal).toBe(false);

        // Open the configuration modal
        wrapper.vm.$refs.blockNavigator.isActive = true;
        wrapper.vm.onSidebarNavigatorClick();
        expect(wrapper.vm.showSidebarNavigatorModal).toBe(true);

        // Uncheck "don't remind me" and confirm the modal
        wrapper.vm.navigatorDontRemind = false;
        wrapper.vm.onSidebarNavigationConfirm();
        expect(localStorage.getItem('cmsNavigatorDontRemind')).toBeFalsy();
        expect(wrapper.vm.showSidebarNavigatorModal).toBe(false);

        // Close the sidebar
        wrapper.vm.$refs.blockNavigator.isActive = false;

        // Reopen the blockNavigator, to see that the modal will still be triggered
        wrapper.vm.$refs.blockNavigator.isActive = true;
        wrapper.vm.onSidebarNavigatorClick();
        expect(localStorage.getItem('cmsNavigatorDontRemind')).toBeFalsy();
        expect(wrapper.vm.showSidebarNavigatorModal).toBe(true);
    });

    it('should fire event to open layout assignment modal', async () => {
        const wrapper = await createWrapper();

        wrapper.findComponent('.sw-cms-sidebar__layout-assignment-open')
            .vm
            .$emit('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('open-layout-assignment')).toBeTruthy();
    });

    it('should show tooltip and disable layout type select when page type is product detail', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            page: {
                ...wrapper.props().page,
                type: 'product_detail',
            },
        });

        await flushPromises();

        const layoutTypeSelect = wrapper.findComponent('sw-select-field-stub[label="sw-cms.detail.label.pageTypeSelection"]');

        expect(layoutTypeSelect.attributes()['tooltip-message'])
            .toBe('sw-cms.detail.tooltip.cannotSelectProductPageLayout');

        expect(layoutTypeSelect.attributes().disabled).toBeTruthy();
    });

    it('should hide tooltip and enable layout type select when page type is not product detail', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            page: {
                ...wrapper.props().page,
                type: 'page',
            },
        });

        await flushPromises();

        const layoutTypeSelect = wrapper.find('sw-select-field-stub[label="sw-cms.detail.label.pageTypeSelection"]');
        const productPageOption = wrapper.find('option[value="product_detail"]');

        expect(layoutTypeSelect.attributes().disabled).toBeUndefined();
        expect(productPageOption.attributes().disabled).toBeDefined();
    });

    it('should emit open-layout-set-as-default when clicking on set as default', async () => {
        global.activeAclRoles = ['system_config.editor'];

        const wrapper = await createWrapper();

        wrapper.findComponent('.sw-cms-sidebar__layout-set-as-default-open')
            .vm
            .$emit('click');

        expect(wrapper.emitted('open-layout-set-as-default')).toStrictEqual([[]]);
    });

    it('should filter blocks based on pageType, category and visibility', async () => {
        const wrapper = await createWrapper({
            cmsBlockRegistry: {
                product_list_block: {
                    name: 'product_list_block',
                    category: 'text',
                    hidden: false,
                },
                listing_block: {
                    name: 'listing_block',
                    category: 'text',
                    hidden: false,
                },
                product_list_hidden_block: {
                    name: 'product_list_hidden_block',
                    category: 'text',
                    hidden: true,
                },
                product_list_different_category: {
                    name: 'product_list_different_category',
                    category: 'product',
                    hidden: false,
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.cmsBlocksBySelectedBlockCategory.map(block => block.name)).toStrictEqual(['product_list_block']);
    });

    it('should render also new block types', async () => {
        const wrapper = await createWrapper({
            cmsBlockRegistry: {
                product_list_block: {
                    name: 'product_list_block',
                    category: 'text',
                    hidden: false,
                },
                listing_block: {
                    name: 'listing_block',
                    category: 'text',
                    hidden: false,
                },
                product_list_hidden_block: {
                    name: 'product_list_hidden_block',
                    category: 'text',
                    hidden: true,
                },
                completely_different_catagory_block: {
                    name: 'completely_different_catagory_block',
                    category: 'completely_different_category',
                    hidden: false,
                },
            },
        });

        await flushPromises();

        const newBlockCategory = wrapper.find('.sw-cms-sidebar__block-category option[value="completely_different_category"]');

        expect(newBlockCategory.exists()).toBeTruthy();
        expect(newBlockCategory.text()).toBe('apps.sw-cms.detail.label.blockCategory.completely_different_category');
    });

    it('should allow editing of the visibility setting of blocks', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent('.sw-cms-sidebar__visibility-config-block').props('visibility')).toStrictEqual({ desktop: true, mobile: true, tablet: true });
        wrapper.findComponent('.sw-cms-sidebar__visibility-config-block').vm.$emit('visibility-change', 'desktop', false);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.selectedBlock.visibility).toStrictEqual({
            desktop: false,
            tablet: true,
            mobile: true,
        });
    });

    it('should allow editing of the visibility setting of sections', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent('.sw-cms-sidebar__visibility-config-section').props('visibility')).toStrictEqual({ desktop: true, mobile: true, tablet: true });
        wrapper.findComponent('.sw-cms-sidebar__visibility-config-section').vm.$emit('visibility-change', 'desktop', false);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.selectedSection.visibility).toStrictEqual({
            desktop: false,
            tablet: true,
            mobile: true,
        });
    });

    it('should apply default data when dropping new elements', async () => {
        const wrapper = await createWrapper();

        const dragData = {
            block: {
                name: 'image',
                label: 'sw-cms.blocks.image.image.label',
                category: 'image',
                component: 'sw-cms-block-image',
                previewComponent: 'sw-cms-preview-image',
                defaultConfig: {
                    marginBottom: '20px',
                    marginTop: '20px',
                    marginLeft: '20px',
                    marginRight: '20px',
                    sizingMode: 'boxed',
                },
                slots: {
                    image: {
                        type: 'image',
                        default: {
                            config: {
                                displayMode: {
                                    source: 'static',
                                    value: 'standard',
                                },
                            },
                            data: {
                                media: {},
                            },
                        },
                    },
                },
            },
        };

        const dropData = {
            dropIndex: 0,
            section: {
                position: 0,
            },
            sectionPosition: 'main',
        };

        wrapper.vm.onBlockStageDrop(dragData, dropData);


        const expectedData = {
            id: null,
            slots: [
                {
                    id: null,
                    blockId: null,
                    slot: 'image',
                    slots: [],
                    type: 'image',
                    config: {
                        displayMode: {
                            source: 'static',
                            value: 'standard',
                        },
                        media: {
                            value: 'preview_mountain_large.jpg',
                            source: 'default',
                        },
                    },
                    data: {
                        media: {
                            value: 'preview_mountain_large.jpg',
                            source: 'default',
                        },
                    },
                },
            ],
            type: 'image',
            visibility: {
                desktop: true,
                tablet: true,
                mobile: true,
            },
            position: 0,
            sectionPosition: 'main',
            marginBottom: '20px',
            marginTop: '20px',
            marginLeft: '20px',
            marginRight: '20px',
            sizingMode: 'boxed',
        };

        expect(JSON.parse(JSON.stringify(wrapper.vm.page.sections[0].blocks[0]))).toStrictEqual(expectedData);
    });
});
