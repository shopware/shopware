import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-category/page/sw-category-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-category-detail'), {
        localVue,
        stubs: {
            'sw-page': {
                template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="side-content"></slot>
    </div>`
            },
            'sw-category-tree': true,
            'sw-button': true,
            'sw-button-process': true
        },
        mocks: {
            $tc: v => v,
            $device: {
                getViewportWidth: () => {},
                getSystemKey: () => {},
                onResize: () => {}
            },
            $store: Shopware.State._store
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            cmsPageService: {},
            cmsService: {},
            repositoryFactory: {},
            seoUrlService: {}
        }
    });
}

describe('src/module/sw-category/page/sw-category-detail', () => {
    beforeAll(() => {
        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            actions: {
                resetCmsPageState: () => {}
            }
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
        wrapper.destroy();
    });

    it('should disable the save button', async () => {
        const wrapper = createWrapper();
        Shopware.State.commit('swCategoryDetail/setActiveCategory', { category: {} });
        await wrapper.setData({
            isLoading: false
        });

        const saveButton = wrapper.find('.sw-category-detail__save-action');

        expect(saveButton.attributes().disabled).toBe('true');
        wrapper.destroy();
    });

    it('should enable the save button', async () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);
        Shopware.State.commit('swCategoryDetail/setActiveCategory', { category: {
            slotConfig: ''
        } });

        await wrapper.setData({
            isLoading: false
        });

        const saveButton = wrapper.find('.sw-category-detail__save-action');

        expect(saveButton.attributes().disabled).toBeUndefined();
        wrapper.destroy();
    });

    it('should not allow to edit', async () => {
        const wrapper = createWrapper([]);
        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes().allowedit).toBeUndefined();
        wrapper.destroy();
    });

    it('should allow to edit', async () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);
        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes().allowedit).toBe('true');
        wrapper.destroy();
    });

    it('should not allow to create', async () => {
        const wrapper = createWrapper([]);
        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes().allowcreate).toBeUndefined();
        wrapper.destroy();
    });

    it('should allow to create', async () => {
        const wrapper = createWrapper([
            'category.creator'
        ]);
        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes().allowcreate).toBe('true');
        wrapper.destroy();
    });

    it('should not allow to delete', async () => {
        const wrapper = createWrapper([]);
        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes().allowdelete).toBeUndefined();
        wrapper.destroy();
    });

    it('should allow to delete', async () => {
        const wrapper = createWrapper([
            'category.deleter'
        ]);
        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes().allowdelete).toBe('true');
        wrapper.destroy();
    });
});
