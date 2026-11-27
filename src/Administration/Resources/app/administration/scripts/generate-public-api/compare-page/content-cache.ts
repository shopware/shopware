/**
 * @sw-package framework
 *
 * Keeps the files a check loaded in the Cache Storage of the browser, by the hash git gives their content. The
 * next check then only loads the files that changed.
 */
import { textOf } from './http';
import type { ContentCache } from './remote-source-tree';

const CACHE_NAME = 'public-api-sources-v1';

/** Cache Storage keys are requests, so a hash gets an address that nothing serves. */
function keyOf(hash: string): string {
    return `https://public-api-sources.invalid/${hash}`;
}

/**
 * @private
 *
 * `storage` is missing where the page has no secure origin, for example when it is opened as a file. The check
 * then runs without a cache.
 */
export function browserContentCache(storage: CacheStorage | undefined): ContentCache | undefined {
    if (storage === undefined) {
        return undefined;
    }

    const opened = storage.open(CACHE_NAME).catch(() => /* The check then runs without a cache. */ undefined);

    return {
        async get(hash) {
            const response = await (await opened)?.match(keyOf(hash));

            return response === undefined ? undefined : textOf(response);
        },

        async set(hash, content) {
            await (await opened)?.put(keyOf(hash), new Response(content));
        },
    };
}
