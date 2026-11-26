/**
 * @sw-package framework
 */
import { runInNewContext } from 'node:vm';
import AssetPathPlugin from './index';

describe.each(['Worker', 'SharedWorker'])('%s URLs', (constructor) => {
    it.each([
        { assetPath: 'https://assets.example.com/asset', basePath: '', expectedPrefix: '' },
        { assetPath: 'https://assets.example.com/asset', basePath: '/staging', expectedPrefix: '/staging' },
        { assetPath: 'https://shop.example.com/asset', basePath: '', expectedPrefix: 'https://shop.example.com/asset' },
        {
            assetPath: 'https://shop.example.com/asset',
            basePath: '/staging',
            expectedPrefix: 'https://shop.example.com/asset',
        },
        { assetPath: '/asset', basePath: '', expectedPrefix: '/asset' },
        { assetPath: '/asset', basePath: '/staging', expectedPrefix: '/asset' },
    ])('resolves $assetPath with base path "$basePath" on the shop origin', ({ assetPath, basePath, expectedPrefix }) => {
        const workerPath = '/bundles/administration/administration/assets/worker.js';
        const bundle = {
            'app.js': {
                type: 'chunk',
                code: `new ${constructor}("${workerPath}")`,
            },
        };
        const generateBundle = AssetPathPlugin().generateBundle as unknown as (
            options: object,
            output: typeof bundle,
        ) => void;

        generateBundle({}, bundle);

        const worker = jest.fn<void, [string]>();
        const location = new URL(`https://shop.example.com${basePath}/admin`);
        runInNewContext(bundle['app.js'].code, {
            window: { __sw__: { assetPath } },
            Shopware: { Context: { api: { basePath } } },
            location,
            URL,
            [constructor]: worker,
        });

        expect(worker).toHaveBeenCalledWith(`${expectedPrefix}${workerPath}`);
        const workerUrl = new URL(worker.mock.calls[0][0], location);
        expect(workerUrl.origin).toBe(location.origin);
    });
});
