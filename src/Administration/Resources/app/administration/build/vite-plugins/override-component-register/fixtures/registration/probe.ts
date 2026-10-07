/**
 * @sw-package framework
 */
import { createRequire } from 'node:module';
import { fileURLToPath, pathToFileURL } from 'node:url';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

type Position = { line: number; column: number };
type RawSourceMap = { sources: string[]; mappings: string };
type MapConsumer = { originalPositionFor(position: Position): { source: string | null; line: number | null } };
type PluginFactory = (options: { pluginEntryFile: string }) => unknown;

const adminRoot = process.env.SHOPWARE_ADMIN_ROOT as string;
const here = path.dirname(fileURLToPath(import.meta.url));
const requireFromAdmin = createRequire(path.join(adminRoot, 'package.json'));
const entryFile = path.join(here, 'src/main.ts');
const entryMarker = 'entry code ran';

const { build } = (await import(pathToFileURL(requireFromAdmin.resolve('vite')).href)) as {
    build: (config: Record<string, unknown>) => Promise<unknown>;
};
const { SourceMapConsumer } = requireFromAdmin('source-map-js') as {
    SourceMapConsumer: new (map: RawSourceMap) => MapConsumer;
};
const { createJiti } = requireFromAdmin('jiti') as {
    createJiti: (id: string) => (modulePath: string) => { default: PluginFactory };
};
const OverrideComponentRegisterPlugin = createJiti(fileURLToPath(import.meta.url))(
    path.join(adminRoot, 'build/vite-plugins/override-component-register/index.ts'),
).default;

/**
 * Stands in for `@vitejs/plugin-vue`: each override module default-exports its path below the Vite root,
 * so the registration calls show which files the glob picked up, and in which order.
 */
const overrideAsPathPlugin = {
    name: 'fixture-override-as-path',
    load(id: string) {
        return id.endsWith('.override.vue') ? `export default ${JSON.stringify(path.relative(here, id))};` : null;
    },
};

/** Converts a string index into the 1-based line / 0-based column a sourcemap consumer expects. */
function positionForIndex(source: string, index: number): Position {
    const lines = source.slice(0, index).split('\n');

    return { line: lines.length, column: lines[lines.length - 1].length };
}

await build({
    configFile: false,
    root: here,
    logLevel: 'silent',
    plugins: [OverrideComponentRegisterPlugin({ pluginEntryFile: entryFile }), overrideAsPathPlugin],
    build: {
        write: true,
        outDir: path.join(here, 'dist'),
        emptyOutDir: true,
        sourcemap: true,
        minify: false,
        rollupOptions: { input: entryFile },
    },
});

const assetDirectory = path.join(here, 'dist/assets');
const chunkFileName = fs.readdirSync(assetDirectory).find((name) => name.endsWith('.js')) as string;
const code = fs.readFileSync(path.join(assetDirectory, chunkFileName), 'utf8');
const map = JSON.parse(fs.readFileSync(path.join(assetDirectory, `${chunkFileName}.map`), 'utf8')) as RawSourceMap;

const calls: string[] = [];
vm.runInNewContext(code, {
    Shopware: {
        Component: { registerOverrideComponent: (override: string) => calls.push(`register ${override}`) },
        entryLoaded: () => calls.push('entry'),
    },
});

const authoredEntry = fs.readFileSync(entryFile, 'utf8');
const generatedIndex = code.indexOf(entryMarker);

process.stdout.write(
    JSON.stringify({
        calls,
        entryMarker: {
            authoredLine: positionForIndex(authoredEntry, authoredEntry.indexOf(entryMarker)).line,
            mapped:
                generatedIndex < 0
                    ? null
                    : new SourceMapConsumer(map).originalPositionFor(positionForIndex(code, generatedIndex)),
        },
    }),
);
