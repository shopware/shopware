/**
 * @sw-package framework
 *
 * Shared mount helper for the sw-admin-menu-item specs. Keep the mocks in one place so the
 * main spec and the split collapsed-sidebar spec cannot drift apart.
 */

import { mount } from '@vue/test-utils';
import AclService from 'src/app/service/acl.service';
import 'src/app/component/structure/sw-admin-menu-item';

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

/**
 * @private
 */
export default createWrapper;
