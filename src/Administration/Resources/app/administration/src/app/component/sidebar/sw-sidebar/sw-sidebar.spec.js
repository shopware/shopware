import { mount } from '@vue/test-utils';

let resizeListener;
const deviceMock = {
    onResize: jest.fn(({ listener }) => {
        resizeListener = listener;
    }),
    removeResizeListener: jest.fn(),
};

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
<sw-sidebar-item title="Filter sidebar item" icon="regular-filter" :tooltip-shortcut="['O', 'F']" />
            `,
            },
            global: {
                stubs: {
                    'sw-sidebar-item': await wrapTestComponent('sw-sidebar-item', { sync: true }),
                    'sw-sidebar-navigation-item': await wrapTestComponent('sw-sidebar-navigation-item', { sync: true }),
                },
                mocks: {
                    $device: deviceMock,
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
 * @sw-package framework
 */
describe('src/app/component/sidebar/sw-sidebar/index.js', () => {
    /** @type VueWrapper */
    let wrapper;

    beforeEach(async () => {
        resizeListener = null;
        deviceMock.onResize.mockClear();
        deviceMock.removeResizeListener.mockClear();

        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should open the sidebar', async () => {
        // Check if the content of the first sidebar item is not visible
        let firstSidebarItemContent = await wrapper.find('.first-sidebar-item-content');
        expect(firstSidebarItemContent.exists()).toBe(false);

        // Open the sidebar
        const firstSidebarNavigationItem = await wrapper.find(
            'button.sw-sidebar-navigation-item[aria-label="First sidebar item"]',
        );
        await firstSidebarNavigationItem.trigger('click');

        // Check if the content of the first sidebar item is visible
        firstSidebarItemContent = await wrapper.find('.first-sidebar-item-content');
        expect(firstSidebarItemContent.text()).toBe('The content of the first sidebar item');
    });

    it('should close the sidebar', async () => {
        // Open the sidebar
        const firstSidebarNavigationItem = await wrapper.find(
            'button.sw-sidebar-navigation-item[aria-label="First sidebar item"]',
        );
        await firstSidebarNavigationItem.trigger('click');

        // Check if the content of the first sidebar item is visible
        let firstSidebarItemContent = await wrapper.find('.first-sidebar-item-content');
        expect(firstSidebarItemContent.text()).toBe('The content of the first sidebar item');

        // Close the sidebar
        const closeButton = await wrapper.find('button[aria-label="sw-sidebar.ariaLabelButtonClose"]');
        await closeButton.trigger('click');

        // Check if the content of the first sidebar item is not visible
        firstSidebarItemContent = await wrapper.find('.first-sidebar-item-content');
        expect(firstSidebarItemContent.exists()).toBe(false);
    });

    it('should keep the active navigation item after resizing', async () => {
        const firstSidebarNavigationItem = await wrapper.find(
            'button.sw-sidebar-navigation-item[aria-label="First sidebar item"]',
        );
        await firstSidebarNavigationItem.trigger('click');

        expect(firstSidebarNavigationItem.classes()).toContain('is--active');
        expect(deviceMock.onResize).toHaveBeenCalledTimes(1);
        expect(resizeListener).toBeDefined();

        resizeListener();
        await flushPromises();

        const resizedSidebarNavigationItem = await wrapper.find(
            'button.sw-sidebar-navigation-item[aria-label="First sidebar item"]',
        );
        expect(resizedSidebarNavigationItem.classes()).toContain('is--active');
    });

    it('should render the navigation item with a tooltip', async () => {
        const button = wrapper.find('button.sw-sidebar-navigation-item[aria-label="First sidebar item"]');

        expect(button.attributes('tooltip-mock-id')).toBeDefined();
        expect(button.attributes('tooltip-mock-message')).toBe('First sidebar item');
        expect(button.attributes('title')).toBeUndefined();
    });

    it('should render shortcut keys in the tooltip content', async () => {
        const filterButton = wrapper.find('button.sw-sidebar-navigation-item[aria-label="Filter sidebar item"]');

        expect(filterButton.attributes('tooltip-mock-message')).toContain('sw-sidebar-navigation-item__tooltip-title');
        expect(filterButton.attributes('tooltip-mock-message')).toContain('Filter sidebar item');
        expect(filterButton.attributes('tooltip-mock-message')).toContain(
            'sw-sidebar-navigation-item__tooltip-shortcut-key',
        );
        expect(filterButton.attributes('tooltip-mock-message')).toContain('aria-label="O"');
        expect(filterButton.attributes('tooltip-mock-message')).toContain('aria-label="F"');
    });
});
