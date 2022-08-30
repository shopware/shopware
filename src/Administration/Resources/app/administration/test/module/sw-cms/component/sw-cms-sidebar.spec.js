import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import 'src/module/sw-cms/component/sw-cms-sidebar';
import 'src/app/component/base/sw-button';
import Vuex from 'vuex';

const { EntityCollection, Entity } = Shopware.Data;

function getBlockData(id = '1a2b', position) {
    return {
        id,
        position,
        sectionPosition: 0,
        slots: [{
            id: 'some-slot-id'
        }]
    };
}

function getBlockCollection(blocks) {
    return new EntityCollection(blocks, 'blocks', null, null, blocks);
}

function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('draggable', {});
    localVue.directive('droppable', {});
    localVue.directive('tooltip', {
        bind(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
        },
        inserted(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
        },
        update(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
        }
    });

    localVue.use(Vuex);
    localStorage.clear();

    return shallowMount(Shopware.Component.build('sw-cms-sidebar'), {
        localVue,
        propsData: {
            page: {
                sections: [
                    new Entity('1111', 'section', {
                        type: 'sidebar',
                        blocks: getBlockCollection([
                            {
                                id: '1a2b',
                                sectionPosition: 'main',
                                type: 'foo-bar',
                                slots: []
                            },
                            {
                                id: '3cd4',
                                sectionPosition: 'sidebar',
                                type: 'foo-bar',
                                slots: []
                            },
                            {
                                id: '5ef6',
                                sectionPosition: 'sidebar',
                                type: 'foo-bar-removed',
                                slots: []
                            },
                            {
                                id: '7gh8',
                                sectionPosition: 'main',
                                type: 'foo-bar-removed',
                                slots: []
                            }
                        ])
                    }),
                    new Entity('2222', 'section', {
                        type: 'sidebar',
                        blocks: getBlockCollection([{
                            id: 'abcd',
                            sectionPosition: 'main',
                            type: 'some-type',
                            slots: []
                        }])
                    })
                ],
                type: 'product_list'
            }
        },
        stubs: {
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-sidebar': true,
            'sw-sidebar-item': true,
            'sw-sidebar-collapse': true,
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
            'sw-checkbox-field': true
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        const mockCollection = [];
                        mockCollection.add = function add(e) { this.push(e); };
                        return {
                            id: null,
                            slots: mockCollection
                        };
                    },
                    save: () => {}
                })
            },
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {
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
                                sizingMode: 'boxed'
                            },
                            slots: {
                                image: {
                                    type: 'image',
                                    default: {
                                        config: {
                                            displayMode: { source: 'static', value: 'standard' }
                                        },
                                        data: {
                                            media: { value: 'preview_mountain_large.jpg', source: 'default' }
                                        }
                                    }
                                }
                            }
                        }
                    };
                }
            }
        }
    });
}

describe('module/sw-cms/component/sw-cms-sidebar', () => {
    beforeAll(() => {
        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            state: {
                isSystemDefaultLanguage: true
            }
        });
    });

    it('should be a Vue.js component', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('disable all sidebar items', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();
        await wrapper.setProps({
            disabled: true
        });

        const sidebarItems = wrapper.findAll('sw-sidebar-item-stub');
        expect(sidebarItems.length).toBe(5);

        sidebarItems.wrappers.forEach(sidebarItem => {
            expect(sidebarItem.attributes().disabled).toBe('true');
        });
    });

    it('enable all sidebar items', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        const sidebarItems = wrapper.findAll('sw-sidebar-item-stub');
        expect(sidebarItems.length).toBe(5);

        sidebarItems.wrappers.forEach(sidebarItem => {
            expect(sidebarItem.attributes().disabled).toBeUndefined();
        });
    });

    it('should correctly adjust the sectionId when drag sorting', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();

        const blockDrag = {
            block: getBlockData('1a2b', 0),
            sectionIndex: 0,
            position: 0
        };
        const blockDrop = {
            block: getBlockData('7gh8', 3),
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
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        const blockDrag = {
            block: getBlockData('1a2b', 0),
            sectionIndex: 0
        };
        const blockDrop = {
            block: getBlockData('7gh8', 2),
            sectionIndex: 1
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

        expect(blockDrag.block.sectionId).toEqual('2222');
    });

    it('should stop prompting a warning when entering the navigator, when "Do not remind me" option has been checked once', () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

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

    it('should continue prompting a warning when entering the navigator, when "Do not remind me" option has not been checked once', () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

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

    it('should keep the id when duplicating blocks', () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        const block = getBlockData();

        const clonedBlock = wrapper.vm.cloneBlock(block, 'random_id');

        expect(clonedBlock.id).toBe('1a2b');
    });

    it('should keep the id when duplicating slots', () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        const block = getBlockData();

        const newBlock = { id: 'random_id', slots: [] };

        wrapper.vm.cloneSlotsInBlock(block, newBlock);

        const [slot] = newBlock.slots;

        expect(slot.id).toBe('some-slot-id');
    });

    it('should fire event to open layout assignment modal', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        wrapper.find('.sw-cms-sidebar__layout-assignment-open').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('open-layout-assignment')).toBeTruthy();
    });

    it('should show tooltip and disable layout type select when page type is product detail', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        await wrapper.setProps({
            page: {
                ...wrapper.props().page,
                type: 'product_detail'
            }
        });


        const layoutTypeSelect = wrapper.find('sw-select-field-stub[label="sw-cms.detail.label.pageType"]');

        expect(layoutTypeSelect.attributes()['tooltip-message'])
            .toBe('sw-cms.detail.tooltip.cannotSelectProductPageLayout');

        expect(layoutTypeSelect.attributes().disabled).toBeTruthy();
    });

    it('should hide tooltip and enable layout type select when page type is not product detail', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        await wrapper.setProps({
            page: {
                ...wrapper.props().page,
                type: 'page'
            }
        });


        const layoutTypeSelect = wrapper.find('sw-select-field-stub[label="sw-cms.detail.label.pageType"]');
        const productPageOption = wrapper.find('option[value="product_detail"]');

        expect(layoutTypeSelect.attributes().disabled).toBeFalsy();
        expect(productPageOption.attributes().disabled).toBeTruthy();
    });

    it('should emit open-layout-set-as-default when clicking on set as default', async () => {
        global.activeAclRoles = ['system_config.editor'];

        const wrapper = createWrapper();

        await wrapper.find('.sw-cms-sidebar__layout-set-as-default-open').trigger('click');

        expect(wrapper.emitted('open-layout-set-as-default')).toStrictEqual([[]]);
    });

    it('should apply default data when dropping new elements', async () => {
        const wrapper = createWrapper();

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
                    sizingMode: 'boxed'
                },
                slots: {
                    image: {
                        type: 'image',
                        default: {
                            config: {
                                displayMode: {
                                    source: 'static',
                                    value: 'standard'
                                }
                            },
                            data: {
                                media: {}
                            }
                        }
                    }
                }
            }
        };

        const dropData = {
            dropIndex: 0,
            section: {
                position: 0,
            },
            sectionPosition: 'main'
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
                            value: 'standard'
                        },
                        media: {
                            value: 'preview_mountain_large.jpg',
                            source: 'default'
                        }
                    },
                    data: {
                        media: {
                            value: 'preview_mountain_large.jpg',
                            source: 'default'
                        }
                    }
                }
            ],
            type: 'image',
            position: 0,
            sectionPosition: 'main',
            marginBottom: '20px',
            marginTop: '20px',
            marginLeft: '20px',
            marginRight: '20px',
            sizingMode: 'boxed'
        };

        expect(JSON.parse(JSON.stringify(wrapper.vm.page.sections[0].blocks[0]))).toStrictEqual(expectedData);
    });
});
