/**
 * @sw-package framework
 *
 * Builds the design ideas for the compare page into `design-ideas/dist/`:
 *
 *     node_modules/.bin/ts-node --transpileOnly --cwd-mode scripts/generate-public-api/compare-page/design-ideas/build.ts
 *
 * One classic script holds Vue, the Meteor components and all directions, so the pages open as files.
 */
import fs from 'node:fs';
import path from 'node:path';
import vue from '@vitejs/plugin-vue';
import { build } from 'vite';

const ROOT = __dirname;
const OUTPUT = path.join(ROOT, 'dist');

function copyFolder(from: string, to: string, extension: string): void {
    fs.mkdirSync(to, { recursive: true });
    fs.readdirSync(from)
        .filter((file) => file.endsWith(extension))
        .forEach((file) => fs.copyFileSync(path.join(from, file), path.join(to, file)));
}

async function buildPages(): Promise<void> {
    await build({
        configFile: false,
        root: ROOT,
        logLevel: 'warn',
        plugins: [vue()],
        define: {
            'process.env.NODE_ENV': JSON.stringify('production'),
            __VUE_OPTIONS_API__: 'true',
            __VUE_PROD_DEVTOOLS__: 'false',
            __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'false',
        },
        build: {
            outDir: OUTPUT,
            emptyOutDir: true,
            cssCodeSplit: false,
            lib: {
                entry: path.join(ROOT, 'src/main.ts'),
                // A page that is opened as a file cannot load a module script.
                formats: ['iife'],
                name: 'DesignIdeas',
                fileName: () => 'design-ideas.js',
                cssFileName: 'design-ideas',
            },
        },
    });

    copyFolder(path.join(ROOT, 'pages'), OUTPUT, '.html');
    copyFolder(path.join(ROOT, 'data'), path.join(OUTPUT, 'data'), '.js');
}

buildPages().catch((error: unknown) => {
    process.stderr.write(`${error instanceof Error ? (error.stack ?? error.message) : String(error)}\n`);
    process.exitCode = 1;
});
