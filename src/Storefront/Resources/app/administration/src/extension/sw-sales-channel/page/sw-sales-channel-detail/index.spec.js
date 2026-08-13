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

    it('assigns theme via the deferred API and reverts the draft association so the entity save does not re-write the mapping', async () => {
        const overrideConfig = getOverrideConfig();
        const themeService = { assignTheme: jest.fn(() => Promise.resolve()) };
        const origin = { extensions: { themes: [{ id: 'old-theme-id' }] } };
        const vm = {
            themeService,
            salesChannel: {
                id: 'sales-channel-id',
                getOrigin: () => origin,
                extensions: { themes: [{ id: 'new-theme-id' }] },
            },
            createNotificationError: jest.fn(),
        };

        await overrideConfig.methods.assignSalesChannelTheme.call(vm);

        expect(themeService.assignTheme).toHaveBeenCalledWith('new-theme-id', 'sales-channel-id');
        // the draft association is reverted to origin, so saving the sales channel writes no mapping
        expect(vm.salesChannel.extensions.themes).toEqual(origin.extensions.themes);
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

    it('notifies and still reverts the draft association when theme assignment fails', async () => {
        const overrideConfig = getOverrideConfig();
        const themeService = { assignTheme: jest.fn(() => Promise.reject(new Error('fail'))) };
        const origin = { extensions: { themes: [{ id: 'old-theme-id' }] } };
        const vm = {
            themeService,
            salesChannel: {
                id: 'sales-channel-id',
                getOrigin: () => origin,
                extensions: { themes: [{ id: 'new-theme-id' }] },
            },
            createNotificationError: jest.fn(),
            $t: (key) => key,
        };

        await overrideConfig.methods.assignSalesChannelTheme.call(vm);

        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-theme-manager.general.messageSaveError',
        });
        // even on failure the mapping must not be written via the entity save
        expect(vm.salesChannel.extensions.themes).toEqual(origin.extensions.themes);
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
