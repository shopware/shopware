/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-category-navigation', {
            sync: true,
        }),
        {
            props: {
                element: {},
            },
            global: {
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                },
            },
        },
    );
}

describe('src/module/sw-cms/elements/sidebar-category-navigation/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/sidebar-category-navigation');
    });

    it('should render the navigation heading above the category placeholders', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('h2').text()).toBe('sw-cms.elements.sidebarCategoryNavigation.component.label.navigation');
        expect(wrapper.find('.sw-cms-el-category-placeholder-listing').exists()).toBe(true);
    });
});
