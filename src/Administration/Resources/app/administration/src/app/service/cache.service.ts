/**
 * @sw-package framework
 */

type CacheKey = unknown[];

type CacheEntry<T = unknown> = {
    key: CacheKey;
    loadedAt: number;
    pending: Promise<T> | null;
    ttl?: number;
    value?: T;
};

type QueryOptions<T> = {
    key: CacheKey;
    fn: () => Promise<T>;
    ttl?: number;
    forceReload?: boolean;
};

type InvalidateOptions = {
    cacheKey: CacheKey;
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class CacheService {
    private static readonly MAX_ENTRIES = 100;

    private entries = new Map<string, CacheEntry>();

    async query<T>({ key, fn, ttl, forceReload = false }: QueryOptions<T>): Promise<T> {
        const cacheId = this.serializeKey(key);
        this.removeExpiredEntries();

        const entry = this.entries.get(cacheId) as CacheEntry<T> | undefined;

        if (!forceReload && entry?.pending) {
            return entry.pending;
        }

        if (!forceReload && entry && this.isFresh(entry, ttl)) {
            return entry.value as T;
        }

        if (!entry) {
            this.removeOldestEntry();
        }

        const nextEntry: CacheEntry<T> = {
            key,
            loadedAt: entry?.loadedAt ?? 0,
            pending: null,
            ttl: ttl ?? entry?.ttl,
            value: entry?.value,
        };

        nextEntry.pending = Promise.resolve()
            .then(fn)
            .then((value) => {
                const currentEntry = this.entries.get(cacheId) as CacheEntry<T> | undefined;

                if (currentEntry !== nextEntry) {
                    return currentEntry ? (currentEntry.pending ?? (currentEntry.value as T)) : value;
                }

                nextEntry.value = value;
                nextEntry.loadedAt = Date.now();
                nextEntry.pending = null;
                this.entries.set(cacheId, nextEntry);

                return value;
            })
            .catch((error) => {
                const currentEntry = this.entries.get(cacheId) as CacheEntry<T> | undefined;

                if (currentEntry !== nextEntry) {
                    if (currentEntry) {
                        return currentEntry.pending ?? (currentEntry.value as T);
                    }

                    throw error;
                }

                nextEntry.pending = null;

                if (nextEntry.value === undefined) {
                    this.entries.delete(cacheId);
                } else {
                    this.entries.set(cacheId, nextEntry);
                }

                throw error;
            });

        this.entries.set(cacheId, nextEntry);

        return nextEntry.pending;
    }

    invalidateCaches({ cacheKey }: InvalidateOptions): void {
        for (const [
            cacheId,
            entry,
        ] of this.entries.entries()) {
            if (this.matchesKey(entry.key, cacheKey)) {
                this.entries.delete(cacheId);
            }
        }
    }

    clear(): void {
        this.entries.clear();
    }

    private isFresh(entry: CacheEntry, ttl?: number): boolean {
        if (entry.value === undefined) {
            return false;
        }

        if (ttl === undefined) {
            return true;
        }

        return Date.now() - entry.loadedAt < ttl;
    }

    private removeExpiredEntries(): void {
        for (const [
            cacheId,
            entry,
        ] of this.entries.entries()) {
            if (!entry.pending && entry.ttl !== undefined && !this.isFresh(entry, entry.ttl)) {
                this.entries.delete(cacheId);
            }
        }
    }

    private removeOldestEntry(): void {
        if (this.entries.size < CacheService.MAX_ENTRIES) {
            return;
        }

        let oldestEntryId: string | undefined;
        let oldestLoadedAt = Infinity;

        for (const [
            cacheId,
            entry,
        ] of this.entries.entries()) {
            if (!entry.pending && entry.loadedAt < oldestLoadedAt) {
                oldestEntryId = cacheId;
                oldestLoadedAt = entry.loadedAt;
            }
        }

        // Preserve pending requests; a bounded cache is preferable to retaining every search variant.
        if (oldestEntryId) {
            this.entries.delete(oldestEntryId);
        }
    }

    private matchesKey(cacheKey: CacheKey, expectedKey: CacheKey): boolean {
        if (expectedKey.length > cacheKey.length) {
            return false;
        }

        return expectedKey.every((value, index) => {
            return cacheKey[index] === value;
        });
    }

    private serializeKey(key: CacheKey): string {
        return JSON.stringify(key);
    }
}
