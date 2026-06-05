/**
 * @sw-package discovery
 */
describe('sw-sales-channel-detail extension', () => {
    function getOverrideConfig() {
        const overrideSpy = jest.spyOn(Shopware.Component, 'override');

        jest.isolateModules(() => {
            require('./index');
        });

        return overrideSpy.mock.calls[0][1];
    }

    it('extends load criteria with themes association', () => {
        const overrideConfig = getOverrideConfig();
        const criteria = { addAssociation: jest.fn() };
        const vm = {
            $super: jest.fn(() => criteria),
        };

        const result = overrideConfig.methods.getLoadSalesChannelCriteria.call(vm);

        expect(result).toBe(criteria);
        expect(criteria.addAssociation).toHaveBeenCalledWith('themes');
    });

    it('adds the theme item to mt-tabs route items', () => {
        const overrideConfig = getOverrideConfig();
        const routerPush = jest.fn(() => Promise.resolve());
        const vm = {
            isLoading: false,
            isProductExportChannel: false,
            $route: {
                params: {
                    id: 'sales-channel-id',
                },
            },
            $router: {
                push: routerPush,
            },
            $super: jest.fn(() => [
                { name: 'sw.sales.channel.detail.base' },
                { name: 'sw.sales.channel.detail.products' },
                { name: 'sw.sales.channel.detail.analytics' },
            ]),
            $t: (snippet) => snippet,
        };

        const tabs = overrideConfig.computed.tabs.call(vm);

        expect(tabs).toEqual([
            expect.objectContaining({
                name: 'sw.sales.channel.detail.base',
            }),
            expect.objectContaining({
                name: 'sw.sales.channel.detail.products',
            }),
            expect.objectContaining({
                label: 'sw-sales-channel.detail.tabTheme',
                name: 'sw.sales.channel.detail.theme',
                disabled: false,
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                name: 'sw.sales.channel.detail.analytics',
            }),
        ]);

        tabs[2].onClick();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.sales.channel.detail.theme',
            params: { id: 'sales-channel-id' },
        });
    });

    it('does not add the theme item for product export channels', () => {
        const overrideConfig = getOverrideConfig();
        const tabs = overrideConfig.computed.tabs.call({
            isProductExportChannel: true,
            $super: jest.fn(() => [
                { name: 'sw.sales.channel.detail.base' },
            ]),
        });

        expect(tabs).toEqual([
            expect.objectContaining({
                name: 'sw.sales.channel.detail.base',
            }),
        ]);
    });

    it('disables the theme item while the sales channel is loading', () => {
        const overrideConfig = getOverrideConfig();
        const routerPush = jest.fn(() => Promise.resolve());
        const tabs = overrideConfig.computed.tabs.call({
            isLoading: true,
            isProductExportChannel: false,
            $route: {
                params: {
                    id: 'sales-channel-id',
                },
            },
            $router: {
                push: routerPush,
            },
            $super: jest.fn(() => [
                { name: 'sw.sales.channel.detail.base' },
            ]),
            $t: (snippet) => snippet,
        });

        tabs[1].onClick();

        expect(tabs[1]).toEqual(expect.objectContaining({
            disabled: true,
        }));
        expect(routerPush).not.toHaveBeenCalled();
    });

    it('assigns theme when sales channel theme changes', async () => {
        const overrideConfig = getOverrideConfig();
        const themeService = { assignTheme: jest.fn(() => Promise.resolve()) };
        const vm = {
            themeService,
            salesChannel: {
                id: 'sales-channel-id',
                getOrigin: () => ({ extensions: { themes: [{ id: 'old-theme-id' }] } }),
                extensions: { themes: [{ id: 'new-theme-id' }] },
            },
            createNotificationError: jest.fn(),
        };

        await overrideConfig.methods.assignSalesChannelTheme.call(vm);

        expect(themeService.assignTheme).toHaveBeenCalledWith('new-theme-id', 'sales-channel-id');
    });

    it('does not assign theme when nothing changed', async () => {
        const overrideConfig = getOverrideConfig();
        const themeService = { assignTheme: jest.fn(() => Promise.resolve()) };
        const vm = {
            themeService,
            salesChannel: {
                id: 'sales-channel-id',
                getOrigin: () => ({ extensions: { themes: [{ id: 'theme-id' }] } }),
                extensions: { themes: [{ id: 'theme-id' }] },
            },
            createNotificationError: jest.fn(),
        };

        await overrideConfig.methods.assignSalesChannelTheme.call(vm);

        expect(themeService.assignTheme).not.toHaveBeenCalled();
    });

    it('notifies when theme assignment fails', async () => {
        const overrideConfig = getOverrideConfig();
        const themeService = { assignTheme: jest.fn(() => Promise.reject(new Error('fail'))) };
        const vm = {
            themeService,
            salesChannel: {
                id: 'sales-channel-id',
                getOrigin: () => ({ extensions: { themes: [{ id: 'old-theme-id' }] } }),
                extensions: { themes: [{ id: 'new-theme-id' }] },
            },
            createNotificationError: jest.fn(),
            $t: (key) => key,
        };

        await overrideConfig.methods.assignSalesChannelTheme.call(vm);

        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-theme-manager.general.messageSaveError',
        });
    });

    it('onSave calls assignment and super handler', async () => {
        const overrideConfig = getOverrideConfig();
        const vm = {
            isLoading: false,
            assignSalesChannelTheme: jest.fn(() => Promise.resolve()),
            $super: jest.fn(() => Promise.resolve()),
        };

        await overrideConfig.methods.onSave.call(vm);

        expect(vm.isLoading).toBe(true);
        expect(vm.assignSalesChannelTheme).toHaveBeenCalled();
        expect(vm.$super).toHaveBeenCalledWith('onSave');
    });
});
