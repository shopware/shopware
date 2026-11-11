/**
 * @sw-package framework
 */
const USER_CONFIG_KEY = 'core.whatsNew';
const DAY_IN_MS = 24 * 60 * 60 * 1000;
const NOW = new Date('2026-11-03T12:00:00Z').getTime();

function historyEntry(version: string, ageInMs: number, type: 'install' | 'update' = 'update') {
    return {
        version,
        recordedAt: new Date(NOW - ageInMs).toISOString(),
        type,
    };
}

describe('whats-new.store', () => {
    const store = Shopware.Store.get('whatsNew');
    const userConfigService = Shopware.Service('userConfigService');

    const originalConfig = { ...Shopware.Context.app.config };
    const badge = { key: 'settings.foo', since: '6.7.17.0' };

    let searchSpy: jest.SpyInstance;
    let upsertSpy: jest.SpyInstance;

    async function loadWith(entries: unknown = undefined): Promise<void> {
        const data = entries === undefined ? {} : { [USER_CONFIG_KEY]: entries };
        searchSpy.mockResolvedValue({ data });

        await store.load();
    }

    beforeEach(() => {
        jest.useFakeTimers({ now: NOW });
        store.$reset();

        searchSpy = jest.spyOn(userConfigService, 'search');
        upsertSpy = jest.spyOn(userConfigService, 'upsert');

        Shopware.Context.app.config.version = '6.7.17.0';
        Shopware.Context.app.config.versionHistory = [
            historyEntry('6.7.16.0', 60 * DAY_IN_MS, 'install'),
            historyEntry('6.7.17.0', DAY_IN_MS),
        ];
    });

    afterEach(() => {
        jest.useRealTimers();

        Shopware.Context.app.config.version = originalConfig.version;
        Shopware.Context.app.config.versionHistory = originalConfig.versionHistory;
    });

    it.each([
        {
            scenario: 'the update that brought its version',
            version: '6.7.17.0',
            versionHistory: [
                historyEntry('6.7.16.0', 60 * DAY_IN_MS, 'install'),
                historyEntry('6.7.17.0', DAY_IN_MS),
            ],
            expected: true,
        },
        {
            scenario: 'an update that skipped its version',
            version: '6.7.19.0',
            versionHistory: [
                historyEntry('6.6.0.0', 400 * DAY_IN_MS, 'install'),
                historyEntry('6.7.19.0', DAY_IN_MS),
            ],
            expected: true,
        },
        {
            scenario: 'a shop installed with its version',
            version: '6.7.17.0',
            versionHistory: [historyEntry('6.7.17.0', DAY_IN_MS, 'install')],
            expected: false,
        },
        {
            scenario: 'a first recorded update that skipped its version',
            version: '6.7.18.0',
            versionHistory: [historyEntry('6.7.18.0', DAY_IN_MS)],
            expected: true,
        },
        {
            scenario: 'a patch update of a shop installed with its version',
            version: '6.7.17.1',
            versionHistory: [
                historyEntry('6.7.17.0', 10 * DAY_IN_MS, 'install'),
                historyEntry('6.7.17.1', DAY_IN_MS),
            ],
            expected: false,
        },
        {
            scenario: 'a shop on an older version',
            version: '6.7.16.0',
            versionHistory: [historyEntry('6.7.16.0', DAY_IN_MS, 'install')],
            expected: false,
        },
        {
            scenario: 'a shop without recorded versions',
            version: '6.7.17.0',
            versionHistory: [],
            expected: false,
        },
        {
            scenario: 'an arrival just under 30 days ago',
            version: '6.7.17.0',
            versionHistory: [
                historyEntry('6.7.16.0', 90 * DAY_IN_MS, 'install'),
                historyEntry('6.7.17.0', 30 * DAY_IN_MS - 1000),
            ],
            expected: true,
        },
        {
            scenario: 'an arrival 30 days ago',
            version: '6.7.17.0',
            versionHistory: [
                historyEntry('6.7.16.0', 90 * DAY_IN_MS, 'install'),
                historyEntry('6.7.17.0', 30 * DAY_IN_MS),
            ],
            expected: false,
        },
        {
            scenario: 'a dev build of its major',
            version: '6.7.9999999.9999999-dev',
            versionHistory: [],
            expected: true,
        },
        {
            scenario: 'a dev build of the next major',
            version: '6.8.9999999.9999999-dev',
            versionHistory: [],
            expected: false,
        },
    ])('treats 6.7.17.0 as new in the shop after $scenario: $expected', ({ version, versionHistory, expected }) => {
        Shopware.Context.app.config.version = version;
        Shopware.Context.app.config.versionHistory = versionHistory;

        expect(store.isNewInShop('6.7.17.0')).toBe(expected);
    });

    it('keeps every badge hidden until the version history arrived', async () => {
        Shopware.Context.app.config.versionHistory = undefined;
        await loadWith();

        expect(store.isVisible(badge)).toBe(false);
    });

    it('keeps every badge hidden when the user config fails to load', async () => {
        searchSpy.mockResolvedValue(undefined);

        await store.load();

        expect(store.isVisible(badge)).toBe(false);
    });

    it('treats a stored empty list as no entries', async () => {
        await loadWith([]);

        expect(store.isVisible(badge)).toBe(true);
    });

    it('stores the first seen time once and hides the badge 7 days later', async () => {
        await loadWith();

        await store.markSeen([badge]);
        jest.setSystemTime(NOW + 7 * DAY_IN_MS - 1);

        await store.markSeen([badge]);
        expect(store.isVisible(badge)).toBe(true);

        jest.setSystemTime(NOW + 7 * DAY_IN_MS);
        expect(store.isVisible(badge)).toBe(false);
        expect(upsertSpy).toHaveBeenCalledTimes(1);
        expect(upsertSpy).toHaveBeenCalledWith({
            [USER_CONFIG_KEY]: {
                'settings.foo': {
                    since: '6.7.17.0',
                    seenAt: NOW,
                },
            },
        });
    });

    it('keeps an item visible for its own number of user days', async () => {
        const item = { ...badge, userDays: 14 };
        await loadWith();

        await store.markSeen([item]);
        jest.setSystemTime(NOW + 14 * DAY_IN_MS - 1);
        expect(store.isVisible(item)).toBe(true);

        jest.setSystemTime(NOW + 14 * DAY_IN_MS);
        expect(store.isVisible(item)).toBe(false);
    });

    it('hides a dismissed item and keeps its first seen time', async () => {
        await loadWith({
            'settings.foo': {
                since: '6.7.17.0',
                seenAt: NOW - DAY_IN_MS,
            },
        });

        await store.dismiss(badge);

        expect(store.isVisible(badge)).toBe(false);
        expect(upsertSpy).toHaveBeenCalledWith({
            [USER_CONFIG_KEY]: {
                'settings.foo': {
                    since: '6.7.17.0',
                    seenAt: NOW - DAY_IN_MS,
                    dismissed: true,
                },
            },
        });
    });

    it('prunes entries outside their shop period on write', async () => {
        await loadWith({
            'settings.expired': {
                since: '6.7.16.0',
                seenAt: NOW,
            },
        });

        await store.markSeen([badge]);

        expect(upsertSpy).toHaveBeenCalledWith({
            [USER_CONFIG_KEY]: {
                'settings.foo': {
                    since: '6.7.17.0',
                    seenAt: NOW,
                },
            },
        });
    });

    it('ignores a failed write', async () => {
        await loadWith();
        upsertSpy.mockRejectedValue(new Error('network'));

        await expect(store.markSeen([badge])).resolves.toBeUndefined();
    });

    it('keeps what the first caller saved when two callers load at the same time', async () => {
        const otherBadge = { key: 'settings.bar', since: '6.7.17.0' };

        let resolveLateLoad: (value: unknown) => void = () => {};
        const lateLoad = new Promise((resolve) => {
            resolveLateLoad = resolve;
        });

        searchSpy.mockResolvedValueOnce({ data: {} }).mockReturnValueOnce(lateLoad);

        await store.markSeen([badge]);
        resolveLateLoad({ data: {} });
        await store.markSeen([otherBadge]);

        expect(upsertSpy).toHaveBeenLastCalledWith({
            [USER_CONFIG_KEY]: {
                'settings.foo': {
                    since: '6.7.17.0',
                    seenAt: NOW,
                },
                'settings.bar': {
                    since: '6.7.17.0',
                    seenAt: NOW,
                },
            },
        });
    });
});
