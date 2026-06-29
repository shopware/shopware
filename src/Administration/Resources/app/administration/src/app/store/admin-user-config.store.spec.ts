/**
 * @sw-package framework
 */
import 'src/app/store/admin-user-config.store';

const userConfigServiceMock = {
    search: jest.fn(),
    upsert: jest.fn(),
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('userConfigService', () => userConfigServiceMock as never);

interface SessionStoreMock {
    setCurrentUser(user: { id: string }): void;
}

describe('admin-user-config.store', () => {
    const store = Shopware.Store.get('adminUserConfig');
    const sessionStore = Shopware.Store.get('session') as unknown as SessionStoreMock;

    beforeEach(() => {
        jest.restoreAllMocks();
        userConfigServiceMock.search.mockReset();
        userConfigServiceMock.upsert.mockReset();
        store.$reset();
        sessionStore.setCurrentUser({ id: 'user-1' });
    });

    it('loads all configs once and reads values from the local state', async () => {
        userConfigServiceMock.search.mockResolvedValue({
            data: {
                foo: 'bar',
            },
        });

        expect(await store.get('foo')).toBe('bar');
        expect(await store.get('foo')).toBe('bar');
        expect(userConfigServiceMock.search).toHaveBeenCalledTimes(1);
        expect(userConfigServiceMock.search).toHaveBeenCalledWith(null);
    });

    it('reuses the pending config load for concurrent first reads', async () => {
        userConfigServiceMock.search.mockResolvedValue({
            data: {
                foo: 'bar',
                baz: 'qux',
            },
        });

        const firstRead = store.get('foo');
        const secondRead = store.get('baz');

        expect(await firstRead).toBe('bar');
        expect(await secondRead).toBe('qux');
        expect(userConfigServiceMock.search).toHaveBeenCalledTimes(1);
    });

    it('updates local values after an upsert without marking a partial cache as fully loaded', async () => {
        userConfigServiceMock.upsert.mockResolvedValue(undefined);

        await store.upsert({ foo: 'bar' });

        expect(userConfigServiceMock.upsert).toHaveBeenCalledWith({ foo: 'bar' });
        expect(store.configs.foo).toBe('bar');
        expect(store.loaded).toBe(false);
        expect(await store.get('foo')).toBe('bar');
    });

    it('reloads configs when the current user changes', async () => {
        userConfigServiceMock.search
            .mockResolvedValueOnce({
                data: {
                    foo: 'first-user',
                },
            })
            .mockResolvedValueOnce({
                data: {
                    foo: 'second-user',
                },
            });

        expect(await store.get('foo')).toBe('first-user');

        sessionStore.setCurrentUser({ id: 'user-2' });

        expect(await store.get('foo')).toBe('second-user');
        expect(userConfigServiceMock.search).toHaveBeenCalledTimes(2);
    });
});
