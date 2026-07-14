/**
 * @sw-package framework
 */

import CacheService from './cache.service';

describe('src/app/service/cache.service.ts', () => {
    let cacheService: CacheService;

    beforeEach(() => {
        cacheService = new CacheService();
        jest.restoreAllMocks();
    });

    it('reuses a cached value while it is fresh', async () => {
        const fn = jest.fn().mockResolvedValue('cached-value');
        jest.spyOn(Date, 'now').mockReturnValue(1000);

        await expect(cacheService.query({ key: ['cache-key'], fn, ttl: 1000 })).resolves.toBe('cached-value');
        await expect(cacheService.query({ key: ['cache-key'], fn, ttl: 1000 })).resolves.toBe('cached-value');

        expect(fn).toHaveBeenCalledTimes(1);
    });

    it('deduplicates concurrent requests for the same key', async () => {
        let resolveRequest: (value: string) => void;
        const fn = jest.fn().mockImplementation(() => {
            return new Promise<string>((resolve) => {
                resolveRequest = resolve;
            });
        });

        const firstRequest = cacheService.query({ key: ['cache-key'], fn });
        const secondRequest = cacheService.query({ key: ['cache-key'], fn });

        await flushPromises();
        resolveRequest!('shared-value');

        await expect(firstRequest).resolves.toBe('shared-value');
        await expect(secondRequest).resolves.toBe('shared-value');
        expect(fn).toHaveBeenCalledTimes(1);
    });

    it('reloads expired entries', async () => {
        const fn = jest.fn().mockResolvedValueOnce('first').mockResolvedValueOnce('second');
        jest.spyOn(Date, 'now').mockReturnValueOnce(1000).mockReturnValueOnce(1201).mockReturnValueOnce(1201);

        await expect(cacheService.query({ key: ['cache-key'], fn, ttl: 100 })).resolves.toBe('first');
        await expect(cacheService.query({ key: ['cache-key'], fn, ttl: 100 })).resolves.toBe('second');
        expect(fn).toHaveBeenCalledTimes(2);
    });

    it('supports forced reloads', async () => {
        const fn = jest.fn().mockResolvedValueOnce('first').mockResolvedValueOnce('second');
        jest.spyOn(Date, 'now').mockReturnValue(1000);

        await expect(cacheService.query({ key: ['cache-key'], fn })).resolves.toBe('first');
        await expect(cacheService.query({ key: ['cache-key'], fn, forceReload: true })).resolves.toBe('second');
        expect(fn).toHaveBeenCalledTimes(2);
    });

    it('returns the latest forced reload result to superseded requests', async () => {
        let resolveFirstRequest: (value: string) => void;
        let resolveSecondRequest: (value: string) => void;
        const fn = jest
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise<string>((resolve) => {
                        resolveFirstRequest = resolve;
                    }),
            )
            .mockImplementationOnce(
                () =>
                    new Promise<string>((resolve) => {
                        resolveSecondRequest = resolve;
                    }),
            );

        const firstRequest = cacheService.query({ key: ['cache-key'], fn });
        await flushPromises();

        const secondRequest = cacheService.query({ key: ['cache-key'], fn, forceReload: true });
        await flushPromises();

        resolveSecondRequest!('fresh-value');
        await expect(secondRequest).resolves.toBe('fresh-value');

        resolveFirstRequest!('stale-value');
        await expect(firstRequest).resolves.toBe('fresh-value');
        await expect(cacheService.query({ key: ['cache-key'], fn })).resolves.toBe('fresh-value');
    });

    it('invalidates exact and child keys', async () => {
        const rootFn = jest.fn().mockResolvedValueOnce('root').mockResolvedValueOnce('root-reloaded');
        const childFn = jest.fn().mockResolvedValueOnce('child').mockResolvedValueOnce('child-reloaded');

        await cacheService.query({ key: ['user-config'], fn: rootFn });
        await cacheService.query({
            key: [
                'user-config',
                'current-user',
            ],
            fn: childFn,
        });

        cacheService.invalidateCaches({ cacheKey: ['user-config'] });

        await expect(cacheService.query({ key: ['user-config'], fn: rootFn })).resolves.toBe('root-reloaded');
        await expect(
            cacheService.query({
                key: [
                    'user-config',
                    'current-user',
                ],
                fn: childFn,
            }),
        ).resolves.toBe('child-reloaded');
    });

    it('clears failed pending entries so retries work', async () => {
        const fn = jest.fn().mockRejectedValueOnce(new Error('nope')).mockResolvedValueOnce('recovered');

        await expect(cacheService.query({ key: ['cache-key'], fn })).rejects.toThrow('nope');
        await expect(cacheService.query({ key: ['cache-key'], fn })).resolves.toBe('recovered');
        expect(fn).toHaveBeenCalledTimes(2);
    });
});
