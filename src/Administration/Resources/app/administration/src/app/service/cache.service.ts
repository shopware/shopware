/**
 * @sw-package framework
 */

type CacheKey = unknown[];

type CacheEntry<T = unknown> = {
    key: CacheKey;
    loadedAt: number;
    pending: Promise<T> | null;
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
    private entries = new Map<string, CacheEntry>();

    async query<T>({ key, fn, ttl, forceReload = false }: QueryOptions<T>): Promise<T> {
        const cacheId = this.serializeKey(key);
        const entry = this.entries.get(cacheId) as CacheEntry<T> | undefined;

        if (!forceReload && entry?.pending) {
            return entry.pending;
        }

        if (!forceReload && entry && this.isFresh(entry, ttl)) {
            return entry.value as T;
        }

        const nextEntry: CacheEntry<T> = {
            key,
            loadedAt: entry?.loadedAt ?? 0,
            pending: null,
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
