/**
 * @sw-package framework
 */
import useUserSettings from './use-user-settings';

function stubShopware(overrides: {
    can?: boolean;
    currentUserId?: string | null;
    services?: Record<string, unknown>;
    repositorySearch?: jest.Mock;
    repositoryCreate?: jest.Mock;
    repositorySave?: jest.Mock;
}): void {
    const {
        can = true,
        currentUserId = 'me',
        services = {},
        repositorySearch,
        repositoryCreate,
        repositorySave,
    } = overrides;

    const repository = {
        search: repositorySearch ?? jest.fn().mockResolvedValue([]),
        create: repositoryCreate ?? jest.fn().mockReturnValue({}),
        save: repositorySave ?? jest.fn().mockResolvedValue('saved'),
    };

    const serviceMap: Record<string, unknown> = {
        acl: { can: jest.fn().mockReturnValue(can) },
        repositoryFactory: { create: jest.fn().mockReturnValue(repository) },
        ...services,
    };

    global.window.Shopware = {
        Service: jest.fn((name: string) => serviceMap[name]),
        Store: { get: jest.fn().mockReturnValue({ currentUser: { id: currentUserId } }) },
        Context: { api: {} },
    } as unknown as typeof Shopware;
}

describe('src/app/composables/use-user-settings', () => {
    it('rejects reads when the acl privilege is missing', async () => {
        stubShopware({ can: false });
        const { getUserSettings } = useUserSettings();

        await expect(getUserSettings('foo')).rejects.toBeUndefined();
    });

    it('getUserSettings uses userConfigService for the current user', async () => {
        const search = jest.fn().mockResolvedValue({ data: { 'my.key': 42 } });

        stubShopware({ currentUserId: 'me', services: { userConfigService: { search } } });
        const { getUserSettings } = useUserSettings();

        await expect(getUserSettings('my.key')).resolves.toBe(42);
        expect(search).toHaveBeenCalledWith(['my.key']);
    });

    it('getUserSettings reads the entity value for a different user', async () => {
        const repositorySearch = jest.fn().mockResolvedValue([{ value: 'other-value' }]);

        stubShopware({ currentUserId: 'me', repositorySearch });
        const { getUserSettings } = useUserSettings();

        await expect(getUserSettings('my.key', 'someone-else')).resolves.toBe('other-value');
    });

    it('saveUserSettings upserts via userConfigService for the current user and namespaces bare keys', async () => {
        const upsert = jest.fn().mockResolvedValue('ok');

        stubShopware({ currentUserId: 'me', services: { userConfigService: { upsert } } });
        const { saveUserSettings } = useUserSettings();

        await saveUserSettings('bareKey', { a: 1 });

        expect(upsert).toHaveBeenCalledWith({ 'custom.bareKey': { a: 1 } });
    });

    it('saveUserSettings rejects without create and update privileges', async () => {
        stubShopware({ can: false });
        const { saveUserSettings } = useUserSettings();

        await expect(saveUserSettings('a.b', {})).rejects.toBeUndefined();
    });

    it('userGridSettingsCriteria filters by key and the resolved user id', () => {
        stubShopware({ currentUserId: 'me' });
        const { userGridSettingsCriteria } = useUserSettings();

        const criteria = userGridSettingsCriteria('a.b');

        expect(criteria.filters).toEqual([
            { type: 'equals', field: 'key', value: 'a.b' },
            { type: 'equals', field: 'userId', value: 'me' },
        ]);
    });
});
