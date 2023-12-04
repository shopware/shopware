import { shallowMount } from '@vue/test-utils_v2';
import Vue from 'vue';

import swGenericCmsPageAssignment from 'src/module/sw-custom-entity/component/sw-generic-cms-page-assignment';

Shopware.Component.register('sw-generic-cms-page-assignment', swGenericCmsPageAssignment);

const pageId = 'TEST-PAGE-ID';
const mockSlotId = 'MOCK-SLOT-ID';

const pageMock = {
    id: pageId,
    name: 'CMS-PAGE-NAME',
    sections: [{
        blocks: [{
            slots: [{
                id: mockSlotId,
                type: 'text-block-mock',
                config: {
                    content: {
                        value: 'Test text',
                        source: 'static',
                    },
                    entity: 'test-entity',
                    required: true,
                    type: 'text',
                },
            }],
        }],
    }],
    type: 'product_list',
};


async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-generic-cms-page-assignment'), {
        stubs: {
            'sw-card': true,
            'sw-cms-list-item': {
                template: '<div class="sw-cms-list-item"></div>',
                props: ['page'],
            },
            'sw-button': {
                template: '<div class="sw-button" @click="$emit(`click`)"></div>',
            },
            'sw-cms-layout-modal': {
                template: '<div class="sw-cms-layout-modal"></div>',
            },
            'sw-cms-page-form': {
                template: '<div class="sw-cms-page-form"></div>',
                props: ['page'],
            },
        },

        provide: {
            cmsPageTypeService: {
                getType(type) {
                    return {
                        title: `sw-cms.detail.label.pageType.${Shopware.Utils.string.camelCase(type)}`,
                    };
                },
            },
            repositoryFactory: {
                create: (name) => {
                    switch (name) {
                        case 'cms_page':
                            return {
                                search: jest.fn(() => Promise.resolve([pageMock])),
                            };
                        default:
                            throw new Error(`No repository for ${name} configured`);
                    }
                },
            },
        },
    });
}

/**
 * @package content
 */
describe('module/sw-custom-entity/component/sw-generic-cms-page-assignment', () => {
    beforeEach(() => {
        if (Shopware.State.get('cmsPageState')) {
            Shopware.State.unregisterModule('cmsPageState');
        }
        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            state: {
                currentPage: null,
            },
            mutations: {
                setCurrentPage(state, page) {
                    state.currentPage = page;
                },
            },
        });
    });

    it('should allow creating a cmsPage', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-generic-cms-page-assignment__create-layout').trigger('click');

        const updateCmsPageIdEvents = wrapper.emitted('create-layout');
        expect(updateCmsPageIdEvents).toHaveLength(1);
        expect(updateCmsPageIdEvents[0]).toEqual([]);
    });

    it('should allow selecting a cmsPage', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-generic-cms-page-assignment__change-layout-action').trigger('click');

        wrapper.get('.sw-cms-layout-modal').vm.$emit('modal-layout-select', pageMock.id);
        await wrapper.vm.$nextTick();

        const updateCmsPageIdEvents = wrapper.emitted('update:cms-page-id');
        expect(updateCmsPageIdEvents).toHaveLength(1);
        expect(updateCmsPageIdEvents[0]).toEqual([pageMock.id]);
    });

    it('should allow closing the sw-cms-layout-modal without making changes', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-generic-cms-page-assignment__change-layout-action').trigger('click');

        wrapper.get('.sw-cms-layout-modal').vm.$emit('modal-close');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-cms-layout-modal').exists()).toBe(false);
        expect(wrapper.emitted()).toEqual({});
    });

    it('should display the previously selected cmsPage', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPageId: pageMock.id,
        });
        await flushPromises();

        expect(wrapper.get('.sw-generic-cms-page-assignment__page-selection-headline').text()).toBe(pageMock.name);
        expect(wrapper.get('.sw-generic-cms-page-assignment__page-selection-subheadline').text()).toBe('sw-cms.detail.label.pageType.productList');
        expect(wrapper.get('.sw-cms-list-item').props('page')).toEqual(pageMock);
        expect(wrapper.get('.sw-cms-page-form').props('page')).toEqual(pageMock);
    });

    it('should allow changing the previously selected cmsPage', async () => {
        const wrapper = await createWrapper();
        const mockPageId2 = 'TEST-PAGE-ID-2';

        await wrapper.setProps({
            cmsPageId: pageMock.id,
        });
        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-generic-cms-page-assignment__change-layout-action').trigger('click');

        wrapper.get('.sw-cms-layout-modal').vm.$emit('modal-layout-select', mockPageId2);
        await wrapper.vm.$nextTick();

        const updateCmsPageIdEvents = wrapper.emitted('update:cms-page-id');
        expect(updateCmsPageIdEvents).toHaveLength(1);
        expect(updateCmsPageIdEvents[0]).toEqual([mockPageId2]);
    });

    it('should allow removing a cmsPage', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPageId: pageMock.id,
        });
        await wrapper.vm.$nextTick();

        await wrapper.get('.sw-generic-cms-page-assignment__layout-reset').trigger('click');
        await wrapper.vm.$nextTick();

        const updateCmsPageIdEvents = wrapper.emitted('update:cms-page-id');
        expect(updateCmsPageIdEvents).toHaveLength(1);
        expect(updateCmsPageIdEvents[0]).toEqual([null]);
    });

    it('should allow opening the cmsPage in the pageBuilder', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPageId: pageMock.id,
        });
        await wrapper.vm.$nextTick();

        await wrapper.get('.sw-generic-cms-page-assignment__open-in-pagebuilder').trigger('click');

        const updateCmsPageIdEvents = wrapper.vm.$router.push;
        expect(updateCmsPageIdEvents).toHaveBeenCalledTimes(1);
        expect(updateCmsPageIdEvents).toHaveBeenCalledWith({
            name: 'sw.cms.detail',
            params: {
                id: 'TEST-PAGE-ID',
            },
        });
    });

    it('should apply slotOverrides', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            cmsPageId: pageMock.id,
            slotOverrides: {
                [mockSlotId]: {
                    content: {
                        value: '<h1>TEST<h1>',
                        source: 'static',
                    },
                },
            },
        });
        await wrapper.vm.$nextTick();

        const pageMockWithOverrides = {
            id: pageMock.id,
            name: 'CMS-PAGE-NAME',
            sections: [{
                blocks: [{
                    slots: [{
                        id: mockSlotId,
                        type: 'text-block-mock',
                        config: {
                            content: {
                                value: '<h1>TEST<h1>',
                                source: 'static',
                            },
                            entity: 'test-entity',
                            required: true,
                            type: 'text',
                        },
                    }],
                }],
            }],
            type: 'product_list',
        };

        expect(wrapper.get('.sw-generic-cms-page-assignment__page-selection-headline').text()).toBe(pageMockWithOverrides.name);
        expect(wrapper.get('.sw-generic-cms-page-assignment__page-selection-subheadline').text()).toBe('sw-cms.detail.label.pageType.productList');
        expect(wrapper.get('.sw-cms-list-item').props('page')).toStrictEqual(pageMockWithOverrides);
        expect(wrapper.get('.sw-cms-page-form').props('page')).toStrictEqual(pageMockWithOverrides);
    });

    it('should emit slotOverrides when the cmsPage is changed', async () => {
        global.Shopware.Data.ChangesetGenerator = class ChangesetGeneratorMock {
            generate() {
                return { changes: {
                    sections: [{
                        blocks: [{
                            slots: [{
                                id: mockSlotId,
                                config: {
                                    content: {
                                        source: 'static',
                                        value: '<h1>TEST</h1>',
                                    },
                                },
                            }],
                        }],
                    }],
                } };
            }
        };

        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPageId: pageMock.id,
            slotOverrides: {
                [mockSlotId]: {
                    content: {
                        value: '<h1>TEST<h1>',
                        source: 'static',
                    },
                },
            },
        });
        await wrapper.vm.$nextTick();

        Vue.set(wrapper.vm.cmsPage.sections[0].blocks[0].slots[0].config.content, 'value', '<h1>TEST2<h1>');
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:slot-overrides')).toHaveLength(1);
        expect(wrapper.emitted('update:slot-overrides')[0]).toEqual([{
            [mockSlotId]: {
                content: {
                    value: '<h1>TEST</h1>',
                    source: 'static',
                },
            },
        }]);
    });
});
