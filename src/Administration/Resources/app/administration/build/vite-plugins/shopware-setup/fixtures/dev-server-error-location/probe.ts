/**
 * @sw-package framework
 */
import { createRequire } from 'node:module';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';

type DevServer = { transformRequest(url: string): Promise<unknown>; close(): Promise<void> };
type ReportedError = { id?: string; loc?: unknown; plugin?: string; frame?: string };

const adminRoot = process.env.SHOPWARE_ADMIN_ROOT as string;
const here = path.dirname(fileURLToPath(import.meta.url));
const requireFromAdmin = createRequire(path.join(adminRoot, 'package.json'));

const { createServer } = (await import(pathToFileURL(requireFromAdmin.resolve('vite')).href)) as {
    createServer: (config: object) => Promise<DevServer>;
};
const { default: vue } = (await import(pathToFileURL(requireFromAdmin.resolve('@vitejs/plugin-vue')).href)) as {
    default: () => unknown;
};
// The shipped plugin, bridged from TypeScript by jiti like build/vue-setup-transform/index.js does.
const { createJiti } = requireFromAdmin('jiti') as {
    createJiti: (id: string) => (modulePath: string) => { default: (options: { administrationRoot: string }) => unknown };
};
const shopwareSetupPlugin = createJiti(fileURLToPath(import.meta.url))(
    path.join(adminRoot, 'build/vite-plugins/shopware-setup/index.ts'),
).default;

const server = await createServer({
    root: here,
    configFile: false,
    logLevel: 'silent',
    plugins: [shopwareSetupPlugin({ administrationRoot: adminRoot }), vue()],
    server: { middlewareMode: true, ws: false, watch: null },
    optimizeDeps: { noDiscovery: true, include: [] },
});

// The browser's request for the importer: its import analysis resolves the broken SFC and fails.
const reported = await server.transformRequest('/src/Entry.ts').then(
    () => null,
    (error: ReportedError) => ({ id: error.id, loc: error.loc, plugin: error.plugin, frame: error.frame }),
);

await server.close();

process.stdout.write(JSON.stringify(reported));
