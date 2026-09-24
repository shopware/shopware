/**
 * @sw-package framework
 */
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

type VueSfcPlugins = () => unknown[];

const adminRoot = process.env.SHOPWARE_ADMIN_ROOT as string;
const here = path.dirname(fileURLToPath(import.meta.url));
const requireFromAdmin = createRequire(path.join(adminRoot, 'package.json'));
const { createJiti } = requireFromAdmin('jiti') as {
    createJiti: (id: string) => (modulePath: string) => { default: VueSfcPlugins };
};

// The shipped SFC pipeline, not a copy, so the fixture proves what the real builds emit.
const vueSfcPlugins = createJiti(fileURLToPath(import.meta.url))(
    path.join(adminRoot, 'build/vite-plugins/vue-sfc/index.ts'),
).default;

/** @private */
export default {
    root: here,
    logLevel: 'silent',
    plugins: vueSfcPlugins(),
    build: {
        // Written to disk because the `.js.map` file is what ships, not the in-memory chunk map.
        write: true,
        outDir: path.join(here, 'dist'),
        emptyOutDir: true,
        sourcemap: true,
        minify: false,
        rollupOptions: {
            input: path.join(here, 'src/Entry.ts'),
            external: ['vue'],
        },
    },
};
