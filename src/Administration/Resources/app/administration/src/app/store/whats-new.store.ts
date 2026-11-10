/**
 * @sw-package framework
 */
import type { ContextState } from 'src/app/composables/use-context';

const USER_CONFIG_KEY = 'core.whatsNew';

const SINCE_PATTERN = /^\d+\.\d+\.\d+\.\d+$/;

const DAY_IN_MS = 24 * 60 * 60 * 1000;

const DURATIONS = {
    SHOP_DAYS: 30,
    USER_DAYS: 7,
} as const;

type VersionHistory = NonNullable<ContextState['app']['config']['versionHistory']>;

/**
 * @private
 */
export interface WhatsNewItem {
    key: string;
    since: string;
    userDays?: number;
}

interface WhatsNewEntry {
    since: string;
    seenAt: number;
    dismissed?: boolean;
}

interface WhatsNewState {
    entries: Record<string, WhatsNewEntry>;
    isLoaded: boolean;
}

function toVersionParts(version: string): number[] {
    return version.replace(/-.*$/, '').split('.').map(Number);
}

function compareVersions(version: string, otherVersion: string): number {
    const parts = toVersionParts(version);
    const otherParts = toVersionParts(otherVersion);

    for (let index = 0; index < 4; index += 1) {
        const difference = (parts[index] ?? 0) - (otherParts[index] ?? 0);

        if (difference !== 0) {
            return difference;
        }
    }

    return 0;
}

function hasSameParts(version: string, otherVersion: string, count: number): boolean {
    const parts = toVersionParts(version);
    const otherParts = toVersionParts(otherVersion);

    return parts.slice(0, count).every((part, index) => part === otherParts[index]);
}

function getArrivedAt(since: string, versionHistory: VersionHistory): number | null {
    const arrivalIndex = versionHistory.findIndex((entry) => compareVersions(entry.version, since) >= 0);

    if (arrivalIndex === -1) {
        return null;
    }

    const arrival = versionHistory[arrivalIndex];

    if (arrivalIndex === 0 && arrival.type === 'install') {
        return null;
    }

    return Date.parse(arrival.recordedAt);
}

function isNewInShop(since: string): boolean {
    const { versionHistory } = Shopware.Context.app.config;
    const currentVersion = Shopware.Context.app.config.version ?? '';

    if (!SINCE_PATTERN.test(since) || !versionHistory) {
        return false;
    }

    if (currentVersion.endsWith('-dev')) {
        return hasSameParts(currentVersion, since, 2);
    }

    const arrivedAt = getArrivedAt(since, versionHistory);

    return arrivedAt !== null && Date.now() - arrivedAt < DURATIONS.SHOP_DAYS * DAY_IN_MS;
}

function toEntries(stored: unknown): Record<string, WhatsNewEntry> {
    if (!Shopware.Utils.types.isPlainObject(stored)) {
        return {};
    }

    return stored as Record<string, WhatsNewEntry>;
}

const whatsNewStore = Shopware.Store.register({
    id: 'whatsNew',

    state: (): WhatsNewState => ({
        entries: {},
        isLoaded: false,
    }),

    getters: {
        isNewInShop: () => {
            return (since: string): boolean => isNewInShop(since);
        },

        isVisible: (state) => {
            return (item: WhatsNewItem): boolean => {
                if (!state.isLoaded || !isNewInShop(item.since)) {
                    return false;
                }

                const entry = state.entries[item.key];

                if (!entry) {
                    return true;
                }

                if (entry.dismissed) {
                    return false;
                }

                return Date.now() - entry.seenAt < (item.userDays ?? DURATIONS.USER_DAYS) * DAY_IN_MS;
            };
        },
    },

    actions: {
        async load(): Promise<void> {
            if (this.isLoaded) {
                return;
            }

            const response = await Shopware.Service('userConfigService').search([USER_CONFIG_KEY]);

            if (!response || this.isLoaded) {
                return;
            }

            this.entries = toEntries(response.data?.[USER_CONFIG_KEY]);
            this.isLoaded = true;
        },

        async markSeen(items: WhatsNewItem[]): Promise<void> {
            await this.load();

            const seenAt = Date.now();
            const seenEntries = items
                .filter((item) => this.isVisible(item) && !this.entries[item.key])
                .map((item): [string, WhatsNewEntry] => [
                    item.key,
                    {
                        since: item.since,
                        seenAt,
                    },
                ]);

            await this.save(seenEntries);
        },

        async dismiss(item: WhatsNewItem): Promise<void> {
            await this.load();

            if (!this.isVisible(item)) {
                return;
            }

            await this.save([
                [
                    item.key,
                    {
                        since: item.since,
                        seenAt: this.entries[item.key]?.seenAt ?? Date.now(),
                        dismissed: true,
                    },
                ],
            ]);
        },

        async save(changedEntries: [string, WhatsNewEntry][]): Promise<void> {
            if (changedEntries.length === 0) {
                return;
            }

            const mergedEntries = {
                ...this.entries,
                ...Object.fromEntries(changedEntries),
            };

            const entriesInPeriod = Object.entries(mergedEntries).filter(([, entry]) => isNewInShop(entry.since));
            this.entries = Object.fromEntries(entriesInPeriod);

            try {
                await Shopware.Service('userConfigService').upsert({
                    [USER_CONFIG_KEY]: this.entries,
                });
            } catch {
                // these items are decoration, a failed write must never reach the user.
            }
        },
    },
});

/**
 * @private
 */
export type WhatsNewStore = ReturnType<typeof whatsNewStore>;

/**
 * @private
 */
export default whatsNewStore;
