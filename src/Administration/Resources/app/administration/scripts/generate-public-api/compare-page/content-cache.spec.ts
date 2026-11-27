/**
 * @sw-package framework
 */
import { browserContentCache } from './content-cache';
import { respond } from './testing/http-response';

// The jsdom of the specs has no Response, and every browser has one.
Object.assign(globalThis, {
    Response: function Response(body: string) {
        return respond(body);
    },
});

function storageWith(open: () => Promise<Cache>): CacheStorage {
    return { open } as unknown as CacheStorage;
}

function memoryStorage(): CacheStorage {
    const responses = new Map<string, Response>();

    return storageWith(() =>
        Promise.resolve({
            match: (key: string) => Promise.resolve(responses.get(key)),
            put: (key: string, response: Response) => {
                responses.set(key, response);

                return Promise.resolve();
            },
        } as unknown as Cache),
    );
}

describe('browserContentCache', () => {
    it('is missing where the browser has no Cache Storage', () => {
        expect(browserContentCache(undefined)).toBeUndefined();
    });

    it('gives back the content of a hash with its byte order mark', async () => {
        const cache = browserContentCache(memoryStorage());

        await cache?.set('abc', '﻿export default {};');

        await expect(cache?.get('abc')).resolves.toBe('﻿export default {};');
    });

    it('knows no content for a hash it was not given', async () => {
        const cache = browserContentCache(memoryStorage());

        await cache?.set('abc', 'export default {};');

        await expect(cache?.get('def')).resolves.toBeUndefined();
    });

    it('stays empty when the browser refuses to open the cache', async () => {
        const cache = browserContentCache(storageWith(() => Promise.reject(new Error('The operation is insecure.'))));

        await cache?.set('abc', 'export default {};');

        await expect(cache?.get('abc')).resolves.toBeUndefined();
    });
});
