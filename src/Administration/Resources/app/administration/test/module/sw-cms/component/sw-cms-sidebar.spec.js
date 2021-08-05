import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import 'src/module/sw-cms/component/sw-cms-sidebar';
import 'src/app/component/base/sw-button';
import Vuex from 'vuex';

const { EntityCollection } = Shopware.Data;

function getBlockData(sectionId = '1111') {
    return {
        id: 'a322757550914445a0ec3c1b23255754',
        slots: [
            {
                blockId: 'a322757550914445a0ec3c1b23255754',
                id: '41d71c21cfb346149c066b4ebeeb0dbf',
                config: {
                    content: {
                        source: 'static',
                        value: '<p>plp<p>'
                    }
                },
                data: null,
                slot: 'content',
                type: 'text'
            }
        ],
        sectionId,
        position: 0,
        sectionPosition: 0
    };
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
                    {
                        id: '1111',
                        type: 'sidebar',
                        blocks: new EntityCollection([
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
                        ])
                    }, {
                        id: '2222',
                        type: 'sidebar',
                        blocks: new EntityCollection([
                            {
                                id: 'abcd',
                                sectionPosition: 'main',
                                type: 'i-dont-care'
                            }
                        ])
                    }
                ]
            }
        },
        stubs: {
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-sidebar': true,
            'sw-sidebar-item': true,
            'sw-sidebar-collapse': true,
            'sw-field': true,
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
                    create: () => ({
                        id: null,
                        slots: []
                    }),
                    save: () => {}
                })
            },
            cmsService: {
                getCmsBlockRegistry: () => ({
                    'foo-bar': {}
                })
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
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('disable all sidebar items', async () => {
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
        const wrapper = createWrapper();

        const sidebarItems = wrapper.findAll('sw-sidebar-item-stub');
        expect(sidebarItems.length).toBe(5);

        sidebarItems.wrappers.forEach(sidebarItem => {
            expect(sidebarItem.attributes().disabled).toBeUndefined();
        });
    });

    it('should correctly adjust the sectionId when drag sorting (cross section)', async () => {
        const wrapper = createWrapper();
        const blockDrag = {
            block: getBlockData('1111'),
            sectionIndex: 0
        };
        const blockDrop = {
            block: getBlockData('2222'),
            sectionIndex: 1
        };

        await wrapper.vm.onBlockDragSort(blockDrag, blockDrop, true);

        expect(wrapper.emitted()['block-navigator-sort'][0]).toEqual([true]);
        expect(blockDrag.block.sectionId).toEqual(blockDrop.block.sectionId);
    });

    it('should stop prompting a warning when entering the navigator, when "Do not remind me" option has been checked once', () => {
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
        const wrapper = createWrapper();

        const block = getBlockData();

        const clonedBlock = wrapper.vm.cloneBlock(block, 'random_id');

        expect(clonedBlock.id).toBe('a322757550914445a0ec3c1b23255754');
    });

    it('should keep the id when duplicating slots', () => {
        const wrapper = createWrapper();

        const block = getBlockData();

        const newBlock = { id: 'random_id', slots: [] };

        wrapper.vm.cloneSlotsInBlock(block, newBlock);

        const [slot] = newBlock.slots;

        expect(slot.id).toBe('41d71c21cfb346149c066b4ebeeb0dbf');
    });

    it('should fire event to open layout assignment modal', async () => {
        const wrapper = createWrapper();

        wrapper.find('.sw-cms-sidebar__layout-assignment-open').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('open-layout-assignment')).toBeTruthy();
    });

    it('should show tooltip and disable layout type select when page type is product detail', async () => {
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
});
