/**
 * @sw-package framework
 */
import { createRequire } from 'node:module';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';

type PluginFactory = (options: { administrationRoot: string }) => unknown;

const adminRoot = process.env.SHOPWARE_ADMIN_ROOT as string;
const here = path.dirname(fileURLToPath(import.meta.url));
const requireFromAdmin = createRequire(path.join(adminRoot, 'package.json'));

/** @private */
export default (async () => {
    const { default: vue } = (await import(pathToFileURL(requireFromAdmin.resolve('@vitejs/plugin-vue')).href)) as {
        default: () => unknown;
    };

    // Exercise the SHIPPED plugin, not a copy: this fixture is the integration proof that the real
    // resolveId/load/generateBundle path composes sourcemaps correctly. jiti bridges the TypeScript
    // source the same way build/vue-setup-transform/index.js does for the transform itself.
    //
    // The build WRITES its output on purpose. Reading the in-memory result would only prove that the
    // remap mutated `chunk.map`, and the `.js.map` file is written from the emitted asset instead - so an
    // in-memory assertion passed while every shipped plugin sourcemap still named the virtual files.
    const { createJiti } = requireFromAdmin('jiti') as {
        createJiti: (id: string) => (modulePath: string) => { default: PluginFactory };
    };
    const jiti = createJiti(fileURLToPath(import.meta.url));
    const ShopwareSetupPlugin = jiti(path.join(adminRoot, 'build/vite-plugins/shopware-setup/index.ts')).default;

    return {
        root: here,
        logLevel: 'silent',
        plugins: [
            ShopwareSetupPlugin({ administrationRoot: adminRoot }),
            vue(),
        ],
        build: {
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
})();
