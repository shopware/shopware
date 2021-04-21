import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import 'src/module/sw-cms/component/sw-cms-sidebar';
import 'src/app/component/base/sw-button';
import Vuex from 'vuex';

function getBlockData() {
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

    return shallowMount(Shopware.Component.build('sw-cms-sidebar'), {
        localVue,
        propsData: {
            page: {
                sections: [
                    {
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
            'sw-entity-single-select': true
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => ({
                        id: null,
                        slots: []
                    })
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
