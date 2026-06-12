/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

describe('components/sw-sidebar-filter-panel', () => {
    let openContentMock;
    let resetAllMock;

    async function createWrapper(activeFilterNumber = 0) {
        openContentMock = jest.fn();
        resetAllMock = jest.fn();

        return mount(await wrapTestComponent('sw-sidebar-filter-panel', { sync: true }), {
            props: {
                activeFilterNumber,
            },
            global: {
                stubs: {
                    'sw-sidebar-item': {
                        inject: ['registerSidebarItem'],
                        props: ['tooltipShortcut'],
                        template: `
                            <div
                                class="sw-sidebar-item-stub"
                                :data-tooltip-shortcut="tooltipShortcut.join(' ')"
                            >
                                <slot name="headline-content"></slot>
                                <slot></slot>
                            </div>
                        `,
                        methods: {
                            openContent: openContentMock,
                        },
                        created() {
                            this.registerSidebarItem({
                                openContent: openContentMock,
                            });
                        },
                    },
                    'sw-filter-panel': {
                        template: '<div></div>',
                        methods: {
                            resetAll: resetAllMock,
                        },
                    },
                },
            },
        });
    }

    it('should register the open filters shortcut', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.$options.shortcuts.OF).toBe('openFilterPanel');
    });

    it('should show the open filters shortcut in the sidebar tooltip', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-sidebar-item-stub').attributes('data-tooltip-shortcut')).toBe('O F');
    });

    it('should open the filter panel', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.openFilterPanel();

        expect(openContentMock).toHaveBeenCalledTimes(1);
    });

    it('should reset all active filters', async () => {
        const wrapper = await createWrapper(1);

        await wrapper.find('a').trigger('click');

        expect(resetAllMock).toHaveBeenCalledTimes(1);
    });
});
