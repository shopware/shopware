import { h } from 'vue';
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
                    'mt-tooltip': {
                        props: {
                            content: {
                                type: String,
                                required: true,
                            },
                            placement: {
                                type: String,
                                required: true,
                            },
                        },
                        setup(props, { slots }) {
                            return () =>
                                h(
                                    'div',
                                    {
                                        class: 'mt-tooltip-stub',
                                        'data-content': props.content,
                                        'data-placement': props.placement,
                                    },
                                    slots.default?.({
                                        id: 'mt-tooltip-stub-trigger',
                                        onFocus: () => {},
                                        onBlur: () => {},
                                        onKeydown: () => {},
                                        onMouseover: () => {},
                                        onMouseleave: () => {},
                                        onMousedown: () => {},
                                        onMouseup: () => {},
                                        'aria-describedby': 'mt-tooltip-stub-content',
                                    }),
                                );
                        },
                    },
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

    it('should render the navigation item with a Meteor tooltip', async () => {
        const tooltip = wrapper.find('.mt-tooltip-stub');
        const firstSidebarNavigationItem = wrapper.find(
            'button.sw-sidebar-navigation-item[aria-label="First sidebar item"]',
        );

        expect(tooltip.attributes('data-content')).toBe('First sidebar item');
        expect(tooltip.attributes('data-placement')).toBe('left');
        expect(firstSidebarNavigationItem.attributes('aria-describedby')).toBe('mt-tooltip-stub-content');
        expect(firstSidebarNavigationItem.attributes('title')).toBeUndefined();
    });

    it('should render shortcut keys in the tooltip content', async () => {
        const tooltip = wrapper.findAll('.mt-tooltip-stub').find((tooltipWrapper) => {
            return tooltipWrapper.attributes('data-content').includes('Filter sidebar item');
        });

        expect(tooltip.attributes('data-content')).toContain('sw-sidebar-navigation-item__tooltip-title');
        expect(tooltip.attributes('data-content')).toContain('Filter sidebar item');
        expect(tooltip.attributes('data-content')).toContain('sw-sidebar-navigation-item__tooltip-shortcut-key');
        expect(tooltip.attributes('data-content')).toContain('aria-label="O"');
        expect(tooltip.attributes('data-content')).toContain('aria-label="F"');
        expect(tooltip.attributes('data-content')).toContain('O');
        expect(tooltip.attributes('data-content')).toContain('F');
    });
});
