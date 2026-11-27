/**
 * @sw-package framework
 *
 * Builds the compare page into `compare-page/dist/`:
 *
 *     npm run build-public-api-page
 *
 * The bundle holds the generator and the break check of this checkout with its major flags. The page loads the
 * sources it compares from GitHub itself, so the folder can be opened as files or served from any static host.
 */
import fs from 'node:fs';
import path from 'node:path';
import { build } from 'vite';
import { ADMINISTRATION_ROOT, currentMajorFlags } from '../source/checkout';
import { repositoryOf, resolveCommit } from '../source/git-source-tree';

const PAGE_ROOT = __dirname;
const OUTPUT = path.join(PAGE_ROOT, 'dist');

async function buildPage(): Promise<void> {
    await build({
        configFile: false,
        root: PAGE_ROOT,
        logLevel: 'warn',
        define: {
            PUBLIC_API_MAJOR_FLAGS: JSON.stringify(currentMajorFlags()),
            // A library build keeps both, and the browser has neither.
            'process.env.NODE_ENV': JSON.stringify('production'),
            __dirname: JSON.stringify('/'),
        },
        resolve: {
            alias: {
                'node:fs': path.join(PAGE_ROOT, 'browser-stubs/fs.ts'),
                'node:path': path.join(PAGE_ROOT, 'browser-stubs/path.ts'),
            },
        },
        build: {
            outDir: OUTPUT,
            emptyOutDir: true,
            lib: {
                entry: path.join(PAGE_ROOT, 'main.ts'),
                // A page that is opened as a file cannot load a module script.
                formats: ['iife'],
                name: 'PublicApiComparePage',
                fileName: () => 'compare-page.js',
            },
        },
    });

    const generator = resolveCommit(repositoryOf(ADMINISTRATION_ROOT), 'HEAD').slice(0, 12);

    fs.writeFileSync(
        path.join(OUTPUT, 'index.html'),
        fs.readFileSync(path.join(PAGE_ROOT, 'index.html'), 'utf8').replace('%GENERATOR%', generator),
    );
}

buildPage().catch((error: unknown) => {
    process.stderr.write(`${error instanceof Error ? (error.stack ?? error.message) : String(error)}\n`);
    process.exitCode = 1;
});
