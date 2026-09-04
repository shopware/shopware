/**
 * @sw-package framework
 */

import { TOOLTIP_OPEN_TRIGGER_PROPS } from 'src/app/component/structure/sw-admin-menu-item';
import createWrapper from './create-wrapper';
import catalogues from './catalogues';

describe('src/app/component/structure/sw-admin-menu-item: collapsed sidebar', () => {
    beforeEach(() => {
        Shopware.Store.get('settingsItems').settingsGroups.shop = [];
        Shopware.Store.get('settingsItems').settingsGroups.system = [];
    });

    describe('collapsed flyout branch navigation', () => {
        // Level-2 row that is both a collapsible and a route, navigating and disclosing on one click
        const branchEntry = {
            id: 'sw-product-reviews',
            moduleType: 'core',
            label: 'Product Reviews',
            path: 'sw.product.reviews.overview',
            parent: 'sw-catalogue',
            position: 15,
            level: 2,
            children: [
                {
                    id: 'sw-product-reviews-all',
                    moduleType: 'core',
                    label: 'All Reviews',
                    path: 'sw.product.reviews.index',
                    parent: 'sw-product-reviews',
                    position: 10,
                    children: [],
                    level: 3,
                },
            ],
        };

        it('should announce the navigation and open the subtree when clicked in the flyout', async () => {
            const wrapper = await createWrapper({
                props: {
                    entry: branchEntry,
                    menuDepth: 2,
                    sidebarExpanded: false,
                },
            });

            await wrapper.find('.sw-admin-menu__navigation-link').trigger('click');

            // Must be emitted from this listener: the route change lands at its end
            expect(wrapper.emitted('flyout-navigate')).toEqual([[{ disclosesChildren: true }]]);
            expect((wrapper.vm as unknown as { collapsibleOpen: boolean }).collapsibleOpen).toBe(true);
        });

        it('should not announce a navigation while the sidebar is expanded', async () => {
            const wrapper = await createWrapper({
                props: {
                    entry: branchEntry,
                    menuDepth: 2,
                    sidebarExpanded: true,
                },
            });

            await wrapper.find('.sw-admin-menu__navigation-link').trigger('click');

            // No flyout exists in the expanded sidebar, so nothing needs suppressing.
            expect(wrapper.emitted('flyout-navigate')).toBeUndefined();
        });

        it('should not announce a navigation for a folder without an own route', async () => {
            const wrapper = await createWrapper({
                props: {
                    entry: catalogues,
                    sidebarExpanded: false,
                },
            });

            await wrapper.find('.sw-admin-menu__navigation-link').trigger('click');

            // Renders the collapsible trigger instead of a router-link, so it never navigates
            expect(wrapper.emitted('flyout-navigate')).toBeUndefined();
        });

        it('should report a leaf click as not disclosing children', async () => {
            const wrapper = await createWrapper({
                props: {
                    entry: branchEntry.children[0],
                    menuDepth: 3,
                    sidebarExpanded: false,
                },
            });

            await wrapper.find('.sw-admin-menu__navigation-link').trigger('click');

            // Releases the pin, and as early as the pin itself: the route change lands first
            expect(wrapper.emitted('flyout-navigate')).toEqual([[{ disclosesChildren: false }]]);
        });
    });

    describe('collapsed flyout keyboard access', () => {
        async function createCollapsedParent(props: Record<string, unknown> = {}) {
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

            await (wrapper as unknown as { setProps: (props: Record<string, unknown>) => Promise<void> }).setProps({
                flyoutActive: false,
            });

            expect(trigger.attributes('aria-expanded')).toBe('false');
            // Falls back to the collapsible's own aria-controls target
            expect(trigger.attributes('aria-controls')).not.toBe('sw-admin-menu-flyout');
        });
    });

    describe('collapsed sidebar tooltip', () => {
        const leafEntry = {
            id: 'sw-dashboard',
            label: 'sw-dashboard.general.mainMenuItemGeneral',
            color: 'var(--color-module-brand-default)',
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

            const tooltip = document.getElementById(row.attributes('aria-describedby') as string);
            expect(tooltip).not.toBeNull();
            expect(tooltip!.textContent).toContain('sw-dashboard.general.mainMenuItemGeneral');
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
            const tooltip = document.getElementById(row.attributes('aria-describedby') as string);

            expect(tooltip!.parentElement!.style.display).toBe('none');

            await row.trigger('mouseover');
            jest.advanceTimersByTime(300);
            await wrapper.vm.$nextTick();

            expect(tooltip!.parentElement!.style.display).not.toBe('none');

            jest.useRealTimers();
        });

        // Focus does not bubble to the non-focusable row — the focusin remap must open it.
        // No fake timers: focus opens without delay, and advancing time would fire the
        // tooltip's mount-time auto-started hover timers, hiding it again.
        it('should open the tooltip when the link inside the row receives keyboard focus', async () => {
            const wrapper = await createWrapper({
                props: {
                    entry: leafEntry,
                    sidebarExpanded: false,
                },
            });

            const row = wrapper.find('.sw-admin-menu__navigation-item-row');
            const tooltip = document.getElementById(row.attributes('aria-describedby') as string);

            expect(tooltip!.parentElement!.style.display).toBe('none');

            await wrapper.find('.sw-admin-menu__navigation-link').trigger('focusin');

            expect(tooltip!.parentElement!.style.display).not.toBe('none');
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

        // Guards TOOLTIP_OPEN_TRIGGER_PROPS: a meteor rename breaks the strip and fails here
        // instead of letting tooltips silently appear on every expanded row
        it('should not open the tooltip on hover or focus while the sidebar is expanded', async () => {
            jest.useFakeTimers();

            const wrapper = await createWrapper({
                props: {
                    entry: leafEntry,
                    sidebarExpanded: true,
                },
            });

            const row = wrapper.find('.sw-admin-menu__navigation-item-row');
            const tooltip = document.getElementById((row.attributes('id') as string).replace('__trigger', '__tooltip'));

            expect(tooltip!.parentElement!.style.display).toBe('none');

            await row.trigger('mouseover');
            await row.trigger('focus');
            jest.advanceTimersByTime(300);
            await wrapper.vm.$nextTick();

            expect(tooltip!.parentElement!.style.display).toBe('none');

            jest.useRealTimers();
        });

        it('should keep the trigger id and the closing handlers when silencing the tooltip', async () => {
            const wrapper = await createWrapper({
                props: {
                    entry: leafEntry,
                    sidebarExpanded: true,
                },
            });

            const stripped = (
                wrapper.vm as unknown as {
                    collapsedTooltipTriggerProps: (attrs: Record<string, unknown>) => Record<string, unknown>;
                }
            ).collapsedTooltipTriggerProps({
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

            // mt-tooltip errors without its trigger id, and the closing handlers keep it hidable
            expect(stripped).toHaveProperty('id', 'trigger-id');
            expect(stripped).toHaveProperty('onMouseleave');
            expect(stripped).toHaveProperty('onBlur');
        });
    });
});
