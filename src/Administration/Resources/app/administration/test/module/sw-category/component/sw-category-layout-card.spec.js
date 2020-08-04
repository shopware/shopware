import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-category/component/sw-category-layout-card';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-category-layout-card'), {
        localVue,
        stubs: {
            'sw-card': true,
            'sw-cms-list-item': true,
            'sw-button': true
        },
        mocks: {
            $route: {
                params: {}
            },
            $tc: v => v
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        propsData: {
            category: {}
        }
    });
}

describe('src/module/sw-category/component/sw-category-layout-card', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should have an enabled cms list item', () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        const cmsListItem = wrapper.find('sw-cms-list-item-stub');

        expect(cmsListItem.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled cms list item', () => {
        const wrapper = createWrapper();

        const cmsListItem = wrapper.find('sw-cms-list-item-stub');

        expect(cmsListItem.attributes().disabled).toBe('true');
    });

    it('should have an enabled button for changing the layout', () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        const changeLayoutButton = wrapper.find('.sw-category-detail-layout__change-layout-action');

        expect(changeLayoutButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled button for changing the layout', () => {
        const wrapper = createWrapper();

        const changeLayoutButton = wrapper.find('.sw-category-detail-layout__change-layout-action');

        expect(changeLayoutButton.attributes().disabled).toBe('true');
    });

    it('should have an enabled button for open the page builder', () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        const pageBuilderButton = wrapper.find('.sw-category-detail-layout__open-in-pagebuilder');

        expect(pageBuilderButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled button for open the page builder', () => {
        const wrapper = createWrapper();

        const pageBuilderButton = wrapper.find('.sw-category-detail-layout__open-in-pagebuilder');

        expect(pageBuilderButton.attributes().disabled).toBe('true');
    });

    it('should have an enabled button for resetting the layout', () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        wrapper.setProps({
            cmsPage: {}
        });

        const resetLayoutButton = wrapper.find('.sw-category-detail-layout__layout-reset');

        expect(resetLayoutButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled button for resetting the layout', () => {
        const wrapper = createWrapper();

        wrapper.setProps({
            cmsPage: {}
        });

        const resetLayoutButton = wrapper.find('.sw-category-detail-layout__layout-reset');

        expect(resetLayoutButton.attributes().disabled).toBe('true');
    });

    it('should show the router link', () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        wrapper.setProps({
            cmsPage: {}
        });

        const routerLink = wrapper.find('.sw-category-layout-card__desc-link');

        expect(routerLink.exists()).toBeTruthy();
    });

    it('should hide the router link', () => {
        const wrapper = createWrapper([]);

        wrapper.setProps({
            cmsPage: {}
        });

        const routerLink = wrapper.find('.sw-category-layout-card__desc-link');

        expect(routerLink.exists()).toBeFalsy();
    });
});
