/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import createMenuService from 'src/app/service/menu.service';

/** fixtures */
import adminModules from '../../../../service/_mocks/adminModules.json';

const menuService = createMenuService(Shopware.Module);
Shopware.Service().register('menuService', () => menuService);

/**
 * @private
 */
export function registerAdminModules() {
    Shopware.Module.getModuleRegistry().clear();
    adminModules.forEach((adminModule) => {
        Shopware.Module.register(adminModule.name, adminModule);
    });
}

/**
 * @private
 */
export default async function createWrapper(options = {}) {
    const router = createRouter({
        routes: [
            ...Shopware.Module.getModuleRoutes(),
            {
                path: '/sw/custom/entity/index',
                name: 'sw.custom.entity.index',
                type: 'core',
                components: { default: 'sw-index' },
                isChildren: false,
                routeKey: 'index',
            },
        ],
        route: {
            meta: {
                $module: {
                    name: '',
                },
            },
        },
        history: createWebHashHistory(),
    });

    router.resolve = jest.fn(() => {
        return {};
    });

    return mount(await wrapTestComponent('sw-admin-menu', { sync: true }), {
        global: {
            stubs: {
                'sw-version': true,
                'sw-admin-menu-item': await wrapTestComponent('sw-admin-menu-item'),
                'mt-loader': true,
                'sw-avatar': true,
                'sw-shortcut-overview': true,
                'router-link': {
                    template: '<a class="router-link" href="#"><slot /></a>',
                },
                'mt-link': true,
                'mt-icon': true,
                'mt-floating-ui': {
                    template: '<div class="mt-floating-ui"><slot v-if="isOpened" /></div>',
                    props: ['isOpened'],
                },
            },
            provide: {
                menuService,
                loginService: {
                    notifyOnLoginListener: () => {},
                },
                userService: {
                    getUser: () => Promise.resolve({ data: { password: '' } }),
                },
                appModulesService: {
                    fetchAppModules: () => Promise.resolve([]),
                },
                systemConfigApiService: {
                    getValues: () => Promise.resolve({}),
                },
                acl: {
                    can: (privilege) => {
                        return privilege !== 'shouldReturnFalse';
                    },
                },
                customEntityDefinitionService: {
                    getMenuEntries: () => {
                        const entityName = 'customEntityName';
                        return [
                            {
                                id: `custom-entity/${entityName}`,
                                label: `${entityName}.moduleTitle`,
                                moduleType: 'plugin',
                                path: 'sw.custom.entity.index',
                                params: {
                                    entityName: entityName,
                                },
                                position: 100,
                                parent: 'sw.second.top.level',
                            },
                        ];
                    },
                },
            },
            mocks: {
                $route: { meta: { $module: { name: '' } } },
                $router: router,
            },
        },
        ...options,
    });
}
