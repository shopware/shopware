/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import AclService from 'src/app/service/acl.service';
import 'src/app/component/structure/sw-admin-menu-item';
import catalogues from '../_sw-admin-menu-item/catalogues';

async function createWrapper({ props = {}, privileges = [], route = {}, routerRoutes = null } = {}) {
    const collectRoutes = (entry) => {
        if (!entry) {
            return [];
        }

        return [
            ...(entry.children ?? []).flatMap((child) => collectRoutes(child)),
            entry,
        ];
    };

    const $router = {
        getRoutes: () =>
            routerRoutes ??
            collectRoutes(props.entry)
                .filter((entry) => entry.path)
                .map((entry) => ({
                    name: entry.path,
                    meta: {
                        privilege: entry.privilege,
                    },
                })),
    };

    const can = (privilege) => {
        if (!privilege) {
            return true;
        }

        return privileges.includes(privilege);
    };

    const aclService = new AclService();

    return mount(await wrapTestComponent('sw-admin-menu-item', { sync: true }), {
        props,
        global: {
            stubs: {
                'sw-admin-menu-item': await Shopware.Component.build('sw-admin-menu-item'),
                'router-link': {
                    template: '<a class="router-link"></a>',
                    props: ['to'],
                },
            },
            mocks: {
                $route: {
                    name: route.name,
                    params: route.params ?? {},
                    matched: route.matched ?? [],
                    meta: { $module: { name: '' }, ...(route.meta ?? {}) },
                },
                $router,
            },
            provide: {
                acl: {
                    can,
                    hasAccessToRoute: (path) => {
                        const match = $router.getRoutes().find((route) => route.name === path);

                        if (!match?.meta) {
                            return true;
                        }

                        return can(match.meta.privilege);
                    },
                    hasActiveSettingModules: aclService.hasActiveSettingModules,
                    state: aclService.state,
                },
                feature: {},
            },
        },
    });
}

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
    });
});
