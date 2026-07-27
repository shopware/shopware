/**
 * @sw-package framework
 */

import { TOOLTIP_OPEN_TRIGGER_PROPS } from 'src/app/component/structure/sw-admin-menu-item';
import createWrapper from '../_sw-admin-menu-item/create-wrapper';
import catalogues from '../_sw-admin-menu-item/catalogues';

describe('src/app/component/structure/sw-admin-menu-item: collapsed sidebar', () => {
    beforeEach(async () => {
        Shopware.Store.get('settingsItems').settingsGroups.shop = [];
        Shopware.Store.get('settingsItems').settingsGroups.system = [];
    });

    describe('collapsed flyout keyboard access', () => {
        async function createCollapsedParent(props = {}) {
            return createWrapper({
                props: {
                    entry: catalogues,
                    sidebarExpanded: false,
                    ...props,
                },
            });
        }

        it('should request the flyout focus on ArrowRight', async () => {
            const wrapper = await createCollapsedParent();

            await wrapper.trigger('keydown', { key: 'ArrowRight' });

            expect(wrapper.emitted('menu-item-hover')).toHaveLength(1);
            expect(wrapper.emitted('flyout-focus-request')).toHaveLength(1);
        });

        it('should request the flyout focus on Enter for entries without an own route', async () => {
            const wrapper = await createCollapsedParent();

            await wrapper.trigger('keydown', { key: 'Enter' });

            expect(wrapper.emitted('flyout-focus-request')).toHaveLength(1);
        });

        it('should keep Enter for navigation on entries with an own route', async () => {
            const wrapper = await createCollapsedParent({
                entry: {
                    ...catalogues,
                    path: 'sw.catalogue.index',
                },
            });

            await wrapper.trigger('keydown', { key: 'Enter' });

            expect(wrapper.emitted('flyout-focus-request')).toBeUndefined();

            await wrapper.trigger('keydown', { key: 'ArrowRight' });

            expect(wrapper.emitted('flyout-focus-request')).toHaveLength(1);
        });

        it('should request closing an open flyout on Escape', async () => {
            const wrapper = await createCollapsedParent({ flyoutActive: true });

            await wrapper.trigger('keydown', { key: 'Escape' });

            expect(wrapper.emitted('flyout-close-request')).toHaveLength(1);
        });

        it('should not handle flyout keys while the sidebar is expanded', async () => {
            const wrapper = await createWrapper({
                props: {
                    entry: catalogues,
                    sidebarExpanded: true,
                },
            });

            await wrapper.trigger('keydown', { key: 'ArrowRight' });

            expect(wrapper.emitted('flyout-focus-request')).toBeUndefined();
        });

        it('should expose the flyout state via aria attributes on the trigger', async () => {
            const wrapper = await createCollapsedParent({ flyoutActive: true });

            const trigger = wrapper.find('.sw-admin-menu__navigation-link');
            expect(trigger.attributes('aria-expanded')).toBe('true');
            expect(trigger.attributes('aria-controls')).toBe('sw-admin-menu-flyout');

            await wrapper.setProps({ flyoutActive: false });

            expect(trigger.attributes('aria-expanded')).toBe('false');
            // Falls back to the collapsible's own aria-controls target
            expect(trigger.attributes('aria-controls')).not.toBe('sw-admin-menu-flyout');
        });
    });

    describe('collapsed sidebar tooltip', () => {
        const leafEntry = {
            id: 'sw-dashboard',
            label: 'sw-dashboard.general.mainMenuItemGeneral',
            color: '#6AD6F0',
            path: 'sw.dashboard.index',
            icon: 'regular-dashboard',
            position: 10,
            level: 1,
            moduleType: 'core',
            children: [],
        };

        it('should bind the mt-tooltip trigger to the row of a first level entry without children while the sidebar is collapsed', async () => {
            const wrapper = await createWrapper({
                props: {
                    entry: leafEntry,
                    sidebarExpanded: false,
                },
            });

            const row = wrapper.find('.sw-admin-menu__navigation-item-row');
            expect(row.attributes('id')).toMatch(/^mt-tooltip--.+__trigger$/);
            expect(row.attributes('aria-describedby')).toMatch(/^mt-tooltip--.+__tooltip$/);

            const tooltip = document.getElementById(row.attributes('aria-describedby'));
            expect(tooltip).not.toBeNull();
            expect(tooltip.textContent).toContain('sw-dashboard.general.mainMenuItemGeneral');
        });

        it('should open the tooltip when the row is hovered while the sidebar is collapsed', async () => {
            jest.useFakeTimers();

            const wrapper = await createWrapper({
                props: {
                    entry: leafEntry,
                    sidebarExpanded: false,
                },
            });

            const row = wrapper.find('.sw-admin-menu__navigation-item-row');
            const tooltip = document.getElementById(row.attributes('aria-describedby'));

            expect(tooltip.parentElement.style.display).toBe('none');

            await row.trigger('mouseover');
            jest.advanceTimersByTime(300);
            await wrapper.vm.$nextTick();

            expect(tooltip.parentElement.style.display).not.toBe('none');

            jest.useRealTimers();
        });

        it('should not bind the tooltip trigger while the sidebar is expanded', async () => {
            const wrapper = await createWrapper({
                props: {
                    entry: leafEntry,
                    sidebarExpanded: true,
                },
            });

            const row = wrapper.find('.sw-admin-menu__navigation-item-row');
            // The trigger id always stays bound, otherwise mt-tooltip cannot find its trigger element
            expect(row.attributes('id')).toMatch(/^mt-tooltip--.+__trigger$/);
            expect(row.attributes('aria-describedby')).toBeUndefined();
        });

        it('should not bind the tooltip trigger on sub menu items', async () => {
            const wrapper = await createWrapper({
                props: {
                    entry: {
                        ...leafEntry,
                        parent: 'sw-catalogue',
                        level: 2,
                    },
                    menuDepth: 2,
                    sidebarExpanded: false,
                },
            });

            const row = wrapper.find('.sw-admin-menu__navigation-item-row');
            expect(row.attributes('aria-describedby')).toBeUndefined();
        });

        // Behavioural counterpart to the collapsed hover test above: the tooltip is silenced by
        // stripping mt-tooltip's opening handlers by name (TOOLTIP_OPEN_TRIGGER_PROPS). Those
        // names are library internals, so if a meteor upgrade renames or adds one, the strip
        // stops working and this test fails instead of tooltips silently appearing on every
        // expanded menu row.
        it('should not open the tooltip on hover or focus while the sidebar is expanded', async () => {
            jest.useFakeTimers();

            const wrapper = await createWrapper({
                props: {
                    entry: leafEntry,
                    sidebarExpanded: true,
                },
            });

            const row = wrapper.find('.sw-admin-menu__navigation-item-row');
            const tooltip = document.getElementById(row.attributes('id').replace('__trigger', '__tooltip'));

            expect(tooltip.parentElement.style.display).toBe('none');

            await row.trigger('mouseover');
            await row.trigger('focus');
            jest.advanceTimersByTime(300);
            await wrapper.vm.$nextTick();

            expect(tooltip.parentElement.style.display).toBe('none');

            jest.useRealTimers();
        });

        it('should keep the trigger id and the closing handlers when silencing the tooltip', async () => {
            const wrapper = await createWrapper({
                props: {
                    entry: leafEntry,
                    sidebarExpanded: true,
                },
            });

            const stripped = wrapper.vm.collapsedTooltipTriggerProps({
                id: 'trigger-id',
                onMouseover: () => {},
                onMouseleave: () => {},
                onFocus: () => {},
                onBlur: () => {},
                'aria-describedby': 'tooltip-id',
            });

            TOOLTIP_OPEN_TRIGGER_PROPS.forEach((prop) => {
                expect(stripped).not.toHaveProperty(prop);
            });

            // mt-tooltip errors without its trigger id, and the closing handlers must keep an
            // already visible tooltip hidable when the sidebar expands while it is hovered.
            expect(stripped).toHaveProperty('id', 'trigger-id');
            expect(stripped).toHaveProperty('onMouseleave');
            expect(stripped).toHaveProperty('onBlur');
        });
    });
});
