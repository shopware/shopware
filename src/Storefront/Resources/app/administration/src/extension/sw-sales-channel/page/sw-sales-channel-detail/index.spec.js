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
            $tc: (key) => key,
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
