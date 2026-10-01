/**
 * @sw-package discovery
 */
import EntityCollection from 'src/core/data/entity-collection.data';

describe('sw-sales-channel-detail extension', () => {
    function getOverrideConfig() {
        const overrideSpy = jest.spyOn(Shopware.Component, 'override');

        jest.isolateModules(() => {
            require('./index');
        });

        return overrideSpy.mock.calls[0][1];
    }

    function themeCollection(...themeIds) {
        return new EntityCollection(
            '/theme',
            'theme',
            Shopware.Context.api,
            null,
            themeIds.map((id) => ({ id })),
        );
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
        const origin = { extensions: { themes: themeCollection('old-theme-id') } };
        const vm = {
            themeService,
            salesChannel: {
                id: 'sales-channel-id',
                getOrigin: () => origin,
                extensions: { themes: themeCollection('new-theme-id') },
            },
            createNotificationError: jest.fn(),
        };

        await overrideConfig.methods.assignSalesChannelTheme.call(vm);

        expect(themeService.assignTheme).toHaveBeenCalledWith('new-theme-id', 'sales-channel-id');
        // the draft association is reverted to origin, so saving the sales channel writes no mapping
        expect([...vm.salesChannel.extensions.themes.getIds()]).toEqual(['old-theme-id']);
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
        const origin = { extensions: { themes: themeCollection('old-theme-id') } };
        const vm = {
            themeService,
            salesChannel: {
                id: 'sales-channel-id',
                getOrigin: () => origin,
                extensions: { themes: themeCollection('new-theme-id') },
            },
            createNotificationError: jest.fn(),
            $t: (key) => key,
        };

        await overrideConfig.methods.assignSalesChannelTheme.call(vm);

        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-theme-manager.general.messageSaveError',
        });
        // even on failure the mapping must not be written via the entity save
        expect([...vm.salesChannel.extensions.themes.getIds()]).toEqual(['old-theme-id']);
    });

    it('does not replace the themes EntityCollection with a plain array when reverting the selection', async () => {
        const overrideConfig = getOverrideConfig();
        const themeService = { assignTheme: jest.fn(() => Promise.resolve()) };
        const originThemes = themeCollection('old-theme-id');
        const draftThemes = themeCollection('new-theme-id');
        const vm = {
            themeService,
            salesChannel: {
                id: 'sales-channel-id',
                getOrigin: () => ({ extensions: { themes: originThemes } }),
                extensions: { themes: draftThemes },
            },
            createNotificationError: jest.fn(),
        };

        await overrideConfig.methods.assignSalesChannelTheme.call(vm);

        const themes = vm.salesChannel.extensions.themes;

        // The changeset generator calls has() and reads source on this association, so assigning a
        // plain array here fails the whole sales channel save.
        expect(themes).toBe(draftThemes);
        expect(themes.has('old-theme-id')).toBe(true);
        expect([...themes.getIds()]).toEqual(['old-theme-id']);
        expect(themes.source).toBe('/theme');
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
