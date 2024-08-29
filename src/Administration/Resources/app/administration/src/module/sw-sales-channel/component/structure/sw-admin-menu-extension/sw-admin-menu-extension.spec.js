/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/structure/sw-admin-menu';
import swAdminMenuExtension from 'src/module/sw-sales-channel/component/structure/sw-admin-menu-extension';
import createMenuService from 'src/app/service/menu.service';

// Turn off known errors
import { missingGetListMethod } from 'src/../test/_helper_/allowedErrors';

Shopware.Component.register('sw-admin-menu-extension', swAdminMenuExtension);

global.allowedErrors = [missingGetListMethod];

const menuService = createMenuService(Shopware.Module);
Shopware.Service().register('menuService', () => menuService);

async function createWrapper() {
    return mount(await wrapTestComponent('sw-admin-menu', { sync: true }), {
        global: {
            stubs: {
                'sw-version': true,
                'sw-icon': true,
                'sw-loader': true,
                'sw-avatar': true,
                'sw-shortcut-overview': true,
                'sw-sales-channel-menu': true,
                'sw-admin-menu-item': true,
            },
            provide: {
                loginService: {
                    notifyOnLoginListener: () => {},
                },
                userService: {
                    getUser: () => Promise.resolve({ data: {} }),
                },
                menuService,
                appModulesService: {
                    fetchAppModules: () => Promise.resolve([]),
                },
                customEntityDefinitionService: {
                    getMenuEntries: () => { return []; },
                },
            },
        },
    });
}

describe('module/sw-sales-channel/component/structure/sw-admin-menu-extension', () => {
    beforeAll(() => {
        Shopware.State.get('session').currentUser = {};
    });

    it('should not show the sw-sales-channel-menu when privilege does not exists', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        const swSalesChannelMenu = wrapper.find('sw-sales-channel-menu-stub');

        expect(swSalesChannelMenu.exists()).toBeFalsy();
    });

    it('should show the sw-sales-channel-menu when privilege exists', async () => {
        global.activeAclRoles = ['sales_channel.viewer'];
        const wrapper = await createWrapper();
        const swSalesChannelMenu = wrapper.find('sw-sales-channel-menu-stub');

        expect(swSalesChannelMenu.exists()).toBeTruthy();
    });
});
