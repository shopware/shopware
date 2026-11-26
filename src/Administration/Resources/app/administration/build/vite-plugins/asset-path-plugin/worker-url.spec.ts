/**
 * @sw-package framework
 */
import { runInNewContext } from 'node:vm';
import AssetPathPlugin from './index';

describe('worker URLs with cross-origin assets', () => {
    it.each([
        ['Worker', ''],
        ['SharedWorker', ''],
        ['Worker', '/staging'],
        ['SharedWorker', '/staging'],
    ])('keeps %s on the shop origin with base path "%s"', (constructor, basePath) => {
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
        runInNewContext(bundle['app.js'].code, {
            window: { __sw__: { assetPath: 'https://assets.example.com/asset' } },
            Shopware: { Context: { api: { basePath } } },
            [constructor]: worker,
        });

        expect(worker).toHaveBeenCalledWith(`${basePath}${workerPath}`);
        const workerUrl = new URL(worker.mock.calls[0][0], 'https://shop.example.com');
        expect(workerUrl.origin).toBe('https://shop.example.com');
        expect(workerUrl.pathname).toBe(`${basePath}${workerPath}`);
    });
});
