import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-category/component/sw-category-layout-card';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-category-layout-card'), {
        stubs: {
            'sw-card': true,
            'sw-cms-list-item': true,
            'sw-icon': true,
            'sw-button': true
        },
        mocks: {
            $route: {
                params: {}
            }
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
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an enabled cms list item', async () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        const cmsListItem = wrapper.find('sw-cms-list-item-stub');

        expect(cmsListItem.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled cms list item', async () => {
        const wrapper = createWrapper();

        const cmsListItem = wrapper.find('sw-cms-list-item-stub');

        expect(cmsListItem.attributes().disabled).toBe('true');
    });

    it('should have an enabled button for changing the layout', async () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        const changeLayoutButton = wrapper.find('.sw-category-detail-layout__change-layout-action');

        expect(changeLayoutButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled button for changing the layout', async () => {
        const wrapper = createWrapper();

        const changeLayoutButton = wrapper.find('.sw-category-detail-layout__change-layout-action');

        expect(changeLayoutButton.attributes().disabled).toBe('true');
    });

    it('should have an enabled button for open the page builder', async () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        const pageBuilderButton = wrapper.find('.sw-category-detail-layout__open-in-pagebuilder');

        expect(pageBuilderButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled button for open the page builder', async () => {
        const wrapper = createWrapper();

        const pageBuilderButton = wrapper.find('.sw-category-detail-layout__open-in-pagebuilder');

        expect(pageBuilderButton.attributes().disabled).toBe('true');
    });

    it('should have an enabled button for resetting the layout', async () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        await wrapper.setProps({
            cmsPage: {}
        });

        const resetLayoutButton = wrapper.find('.sw-category-detail-layout__layout-reset');

        expect(resetLayoutButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled button for resetting the layout', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            cmsPage: {}
        });

        const resetLayoutButton = wrapper.find('.sw-category-detail-layout__layout-reset');

        expect(resetLayoutButton.attributes().disabled).toBe('true');
    });
});
