import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-sidebar', {
            sync: true,
        }),
        {
            slots: {
                default: `
<sw-sidebar-item title="First sidebar item" icon="regular-image">
    <p class="first-sidebar-item-content">The content of the first sidebar item</p>
</sw-sidebar-item>
            `,
            },
            global: {
                stubs: {
                    'sw-sidebar-item': await wrapTestComponent('sw-sidebar-item', { sync: true }),
                    'sw-sidebar-navigation-item': await wrapTestComponent('sw-sidebar-navigation-item', { sync: true }),
                    'sw-icon': true,
                },
                provide: {
                    setSwPageSidebarOffset: () => {},
                    removeSwPageSidebarOffset: () => {},
                },
            },
        },
    );
}

/**
 * @package admin
 */
describe('src/app/component/sidebar/sw-sidebar/index.js', () => {
    /** @type VueWrapper */
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should open the sidebar', async () => {
        // Check if the content of the first sidebar item is not visible
        let firstSidebarItemContent = await wrapper.find('.first-sidebar-item-content');
        expect(firstSidebarItemContent.exists()).toBe(false);

        // Open the sidebar
        const firstSidebarNavigationItem = await wrapper.find(
            'button.sw-sidebar-navigation-item[title="First sidebar item"]',
        );
        await firstSidebarNavigationItem.trigger('click');

        // Check if the content of the first sidebar item is visible
        firstSidebarItemContent = await wrapper.find('.first-sidebar-item-content');
        expect(firstSidebarItemContent.text()).toBe('The content of the first sidebar item');
    });

    it('should close the sidebar', async () => {
        // Open the sidebar
        const firstSidebarNavigationItem = await wrapper.find(
            'button.sw-sidebar-navigation-item[title="First sidebar item"]',
        );
        await firstSidebarNavigationItem.trigger('click');

        // Check if the content of the first sidebar item is visible
        let firstSidebarItemContent = await wrapper.find('.first-sidebar-item-content');
        expect(firstSidebarItemContent.text()).toBe('The content of the first sidebar item');

        // Close the sidebar
        const closeButton = await wrapper.find('button[aria-label="closeContent"]');
        await closeButton.trigger('click');

        // Check if the content of the first sidebar item is not visible
        firstSidebarItemContent = await wrapper.find('.first-sidebar-item-content');
        expect(firstSidebarItemContent.exists()).toBe(false);
    });
});
