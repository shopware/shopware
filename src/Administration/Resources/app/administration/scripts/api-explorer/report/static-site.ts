/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import { APP_ROOT, loadVite, SHARED_VITE_CONFIG } from '../server/vite-ui';
import { WORKTREE_REF } from '../snapshot/types';
import { STATIC_REPORT_GLOBAL, type StaticReportData } from './types';

/** Writes `app.js` (a classic IIFE script) and `app.css` into `outDir`. */
export type StaticAppBuilder = (outDir: string) => Promise<void>;

export const buildStaticApp: StaticAppBuilder = async (outDir) => {
    const { build } = await loadVite();

    await build({
        ...SHARED_VITE_CONFIG,
        base: './',
        mode: 'production',
        logLevel: 'warn',
        build: {
            outDir,
            emptyOutDir: false,
            copyPublicDir: false,
            modulePreload: false,
            cssCodeSplit: false,
            reportCompressedSize: false,
            rollupOptions: {
                input: path.join(APP_ROOT, 'main.ts'),
                output: {
                    format: 'iife',
                    inlineDynamicImports: true,
                    entryFileNames: 'app.js',
                    assetFileNames: (asset) =>
                        asset.names.some((name) => name.endsWith('.css')) ? 'app.css' : 'assets/[name]-[hash][extname]',
                },
            },
        },
    });
};

function escapeHtml(text: string): string {
    return text.replace(/[&<>"]/g, (char) => `&#${char.charCodeAt(0)};`);
}

export function renderDataScript(data: StaticReportData): string {
    return `window.${STATIC_REPORT_GLOBAL} = ${JSON.stringify(data)};\n`;
}

/** Classic scripts only: browsers block module scripts and `fetch` on `file://`. */
export function renderIndexHtml(data: StaticReportData): string {
    const headName =
        data.head.ref === WORKTREE_REF
            ? `Checked-out commit${data.worktreeCommit ? ` @ ${data.worktreeCommit.slice(0, 7)}` : ''}`
            : data.head.ref;
    const title = `Admin API Explorer: ${data.base.ref} vs. ${headName}`;

    return [
        '<!doctype html>',
        '<html lang="en">',
        '<head>',
        '    <meta charset="utf-8">',
        '    <meta name="viewport" content="width=device-width, initial-scale=1">',
        `    <title>${escapeHtml(title)}</title>`,
        '    <link rel="stylesheet" href="./app.css">',
        '</head>',
        '<body>',
        '    <div id="app"></div>',
        '    <script src="./data.js"></script>',
        '    <script src="./app.js"></script>',
        '</body>',
        '</html>',
        '',
    ].join('\n');
}

export async function writeStaticSite(
    outDir: string,
    data: StaticReportData,
    buildApp: StaticAppBuilder = buildStaticApp,
): Promise<void> {
    fs.mkdirSync(outDir, { recursive: true });
    await buildApp(outDir);
    fs.writeFileSync(path.join(outDir, 'data.js'), renderDataScript(data));
    fs.writeFileSync(path.join(outDir, 'index.html'), renderIndexHtml(data));
}
