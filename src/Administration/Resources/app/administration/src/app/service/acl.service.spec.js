/**
 * @package admin
 */

import AclService from 'src/app/service/acl.service';

describe('src/app/service/acl.service.ts', () => {
    beforeEach(() => {
        Shopware.Application.view = {};
        Shopware.Application.view.root = {};
        Shopware.Application.view.root.$router = {};
        Shopware.Application.view.root.$router.resolve = () => ({});
    });

    it('should be an admin', async () => {
        const aclService = new AclService({
            get: () => ({ currentUser: { admin: true } }),
        });

        expect(aclService.isAdmin()).toBe(true);
    });

    it('should not be an admin', async () => {
        const aclService = new AclService({
            get: () => ({ currentUser: { admin: false } }),
        });

        expect(aclService.isAdmin()).toBe(false);
    });

    it('should not be an admin if the store is empty', async () => {
        const aclService = new AclService({
            get: () => ({ currentUser: null }),
        });

        expect(aclService.isAdmin()).toBe(false);
    });

    it('should allow every privilege as an admin', async () => {
        const aclService = new AclService({
            get: () => ({ currentUser: { admin: true } }),
            getters: {
                userPrivileges: [],
            },
        });

        expect(aclService.can('system.clear_cache')).toBe(true);
    });

    it('should disallow when privilege does not exists', async () => {
        const aclService = new AclService({
            get: () => ({ currentUser: { admin: false } }),
            getters: {
                userPrivileges: [],
            },
        });

        expect(aclService.can('system.clear_cache')).toBeFalsy();
    });

    it('should allow when privilege exists', async () => {
        const aclService = new AclService({
            get: () => ({ currentUser: { admin: false } }),
            getters: {
                userPrivileges: ['system.clear_cache'],
            },
        });

        expect(aclService.can('system.clear_cache')).toBe(true);
    });

    it('should return all privileges', async () => {
        const aclService = new AclService({
            get: () => ({ currentUser: { admin: false } }),
            getters: {
                userPrivileges: [
                    'system.clear_cache',
                    'orders.create_discounts',
                ],
            },
        });

        expect(aclService.privileges).toContain('system.clear_cache');
        expect(aclService.privileges).toContain('orders.create_discounts');
    });

    it('should return true if router is undefined', async () => {
        Shopware.Application.view.root.$router = null;

        const aclService = new AclService({
            get: () => ({ currentUser: { admin: false } }),
            getters: {
                userPrivileges: [
                    'product.viewer',
                ],
            },
        });

        expect(aclService.hasAccessToRoute('sw.product.index')).toBe(true);
    });

    it('should have access to the route when no privilege exists', async () => {
        Shopware.Application.view.root.$router.resolve = () => ({});

        const aclService = new AclService({
            get: () => ({ currentUser: { admin: false } }),
            getters: {
                userPrivileges: [
                    'product.viewer',
                ],
            },
        });

        expect(aclService.hasAccessToRoute('sw.product.index')).toBe(true);
    });

    it('should not have access to the route when privilege not matches', async () => {
        Shopware.Application.view.root.$router.resolve = () => ({
            meta: {
                privilege: 'category.viewer',
            },
        });

        const aclService = new AclService({
            get: () => ({ currentUser: { admin: false } }),
            getters: {
                userPrivileges: [
                    'product.viewer',
                ],
            },
        });

        expect(aclService.hasAccessToRoute('sw.product.index')).toBeFalsy();
    });

    it('should have access to the route when privilege matches', async () => {
        Shopware.Application.view.root.$router.resolve = () => ({
            meta: {
                privilege: 'product.viewer',
            },
        });

        const aclService = new AclService({
            get: () => ({ currentUser: { admin: false } }),
            getters: {
                userPrivileges: [
                    'product.viewer',
                ],
            },
        });

        expect(aclService.hasAccessToRoute('sw.product.index')).toBe(true);
    });

    it('should have access to the settings route when user has any access to settings', async () => {
        const aclService = new AclService({
            get: (key) => {
                if (key === 'settingsItems') {
                    return {
                        settingsGroups: {
                            shop: [
                                {
                                    group: 'shop',
                                    icon: 'default-chart-pie',
                                    id: 'sw-settings-tax',
                                    label: 'sw-settings-tax.general.mainMenuItemGeneral',
                                    name: 'settings-tax',
                                    privilege: 'tax.viewer',
                                    to: 'sw.settings.tax.index',
                                },
                            ],
                            system: [],
                        },
                    };
                }

                return { currentUser: { admin: false } };
            },
            getters: {
                userPrivileges: ['tax.viewer'],
            },
        });

        expect(aclService.hasAccessToRoute('.sw.settings.index')).toBe(true);
        expect(aclService.hasAccessToRoute('/sw/settings/index')).toBe(true);
    });

    it('should have access to the settings route when user has no access to settings', async () => {
        const aclService = new AclService({
            get: (key) => {
                if (key === 'settingsItems') {
                    return {
                        settingsGroups: {
                            shop: [
                                {
                                    group: 'shop',
                                    icon: 'default-chart-pie',
                                    id: 'sw-settings-tax',
                                    label: 'sw-settings-tax.general.mainMenuItemGeneral',
                                    name: 'settings-tax',
                                    privilege: 'tax.viewer',
                                    to: 'sw.settings.tax.index',
                                },
                            ],
                            system: [],
                        },
                    };
                }

                return { currentUser: { admin: false } };
            },
            getters: {
                userPrivileges: [],
            },
        });

        expect(aclService.hasAccessToRoute('.sw.settings.index')).toBe(false);
        expect(aclService.hasAccessToRoute('/sw/settings/index')).toBe(false);
    });
});
