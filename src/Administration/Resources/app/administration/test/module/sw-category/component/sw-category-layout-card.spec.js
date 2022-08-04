import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-category/component/sw-category-layout-card';

const categoryId = 'some-category-id';
const cmsPageId = 'some-cms-page-id';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-category-layout-card'), {
        stubs: {
            'sw-button': {
                template: '<button @click="$emit(`click`)"></button>',
                props: ['disabled']
            },
            'sw-cms-list-item': {
                template: '<div class="sw-cms-list-item"></div>',
                props: ['disabled']
            },
            'sw-card': true,
            'sw-icon': true,
        },
        mocks: {
            $route: {
                params: {}
            }
        },
        propsData: {
            category: {
                id: categoryId,
                cmsPageId
            }
        }
    });
}

describe('src/module/sw-category/component/sw-category-layout-card', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an enabled cms list item', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = createWrapper();

        const cmsListItem = wrapper.find('.sw-cms-list-item');

        expect(cmsListItem.props('disabled')).toBe(false);
    });

    it('should have an disabled cms list item', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        const cmsListItem = wrapper.find('.sw-cms-list-item');

        expect(cmsListItem.props('disabled')).toBe(true);
    });

    it('should have an enabled button for changing the layout', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = createWrapper();

        const changeLayoutButton = wrapper.find('.sw-category-detail-layout__change-layout-action');

        expect(changeLayoutButton.props('disabled')).toBe(false);
    });

    it('should have an disabled button for changing the layout', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        const changeLayoutButton = wrapper.find('.sw-category-detail-layout__change-layout-action');

        expect(changeLayoutButton.props('disabled')).toBe(true);
    });

    it('should have an enabled button for open the page builder', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = createWrapper();

        const pageBuilderButton = wrapper.find('.sw-category-detail-layout__open-in-pagebuilder');

        expect(pageBuilderButton.props('disabled')).toBe(false);
    });

    it('should have an disabled button for open the page builder', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        const pageBuilderButton = wrapper.find('.sw-category-detail-layout__open-in-pagebuilder');

        expect(pageBuilderButton.props('disabled')).toBe(true);
    });

    it('should have an enabled button for resetting the layout', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = createWrapper();

        await wrapper.setProps({
            cmsPage: {}
        });

        const resetLayoutButton = wrapper.find('.sw-category-detail-layout__layout-reset');

        expect(resetLayoutButton.props('disabled')).toBe(false);
    });

    it('should have an disabled button for resetting the layout', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        await wrapper.setProps({
            cmsPage: {}
        });

        const resetLayoutButton = wrapper.find('.sw-category-detail-layout__layout-reset');

        expect(resetLayoutButton.props('disabled')).toBe(true);
    });

    it('should pass the category id to the sw.cms.create route', async () => {
        const wrapper = createWrapper();

        await wrapper.find('.sw-category-detail-layout__open-in-pagebuilder').trigger('click');

        const routerPush = wrapper.vm.$router.push;

        expect(routerPush).toHaveBeenCalledTimes(1);
        expect(routerPush).toHaveBeenLastCalledWith({
            name: 'sw.cms.create',
            params: {
                id: categoryId, type: 'category'
            }
        });
    });

    it('should pass the category id to the sw.cms.create route', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({ cmsPage: { id: cmsPageId } });

        await wrapper.find('.sw-category-detail-layout__open-in-pagebuilder').trigger('click');

        const routerPush = wrapper.vm.$router.push;

        expect(routerPush).toHaveBeenCalledTimes(1);
        expect(routerPush).toHaveBeenLastCalledWith({ name: 'sw.cms.detail', params: { id: cmsPageId } });
    });
});
