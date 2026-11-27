/**
 * @sw-package framework
 */
import { rollup } from 'rollup';
import type { OutputChunk } from 'rollup';
import AssetPathPlugin from './index';

async function build(plugin = AssetPathPlugin()): Promise<OutputChunk> {
    const bundle = await rollup({
        input: 'entry',
        plugins: [
            {
                name: 'worker-fixture',
                resolveId: () => '\0entry',
                load: () =>
                    'new SharedWorker("/bundles/administration/administration/assets/worker.js");' +
                    'new Worker("/bundles/extension/administration/assets/worker.js");',
            },
            plugin,
        ],
    });

    try {
        const { output } = await bundle.generate({ format: 'es', entryFileNames: '[name]-[hash].js' });

        return output[0];
    } finally {
        await bundle.close();
    }
}

describe('worker URL rewrite cache invalidation', () => {
    it('changes the chunk filename when the worker URL rewrite changes', async () => {
        const administration = await build();
        const extension = await build(AssetPathPlugin('extension'));

        expect(administration.code).not.toBe(extension.code);
        expect(administration.fileName).not.toBe(extension.fileName);
    });

    it('invalidates previously cached chunks and keeps identical builds stable', async () => {
        const withoutHash = AssetPathPlugin();
        delete withoutHash.augmentChunkHash;

        const cached = await build(withoutHash);
        const current = await build();
        const repeated = await build();

        expect(current.code).toBe(cached.code);
        expect(current.fileName).not.toBe(cached.fileName);
        expect(repeated.fileName).toBe(current.fileName);
    });
});
