// @vitest-environment node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { rolldown } from 'rolldown';
import type { OutputAsset, OutputChunk, RolldownOutput } from 'rolldown';

import { componentMapPlugin } from './component-map-plugin';

/**
 * Runs `componentMapPlugin` against a fixture tree and returns the generated
 * bundle plus a few convenience views on it. Fixtures are written to disk so
 * that Rolldown's real `node_modules` resolver (and therefore the module-id
 * shape the plugin relies on) is exercised end-to-end.
 */
type BuildResult = {
    chunks: OutputChunk[];
    assets: OutputAsset[];
    vendorMap: Record<string, string>;
    manifest: Record<string, unknown>;
    entryByName: (entryName: string) => OutputChunk | undefined;
};

type Fixture = {
    /** Entry files to pass to Rolldown, keyed by entry name. */
    entries: Record<string, string>;
    /** Virtual-fs files to write before running the build. Keys are paths relative to the fixture root. */
    files: Record<string, string>;
};

function isStringRecord(value: unknown): value is Record<string, string> {
    if (!value || typeof value !== 'object') {
        return false;
    }

    return Object.values(value).every(item => typeof item === 'string');
}

async function build(fixtureRoot: string, fixture: Fixture): Promise<BuildResult> {
    for (const [relPath, content] of Object.entries(fixture.files)) {
        const absPath = path.join(fixtureRoot, relPath);
        fs.mkdirSync(path.dirname(absPath), { recursive: true });
        fs.writeFileSync(absPath, content);
    }

    const input: Record<string, string> = {};
    for (const [name, relPath] of Object.entries(fixture.entries)) {
        input[name] = path.join(fixtureRoot, relPath);
    }

    const bundle = await rolldown({
        input,
        cwd: fixtureRoot,
        plugins: [componentMapPlugin()],
    });

    let output: RolldownOutput;
    try {
        output = await bundle.generate({
            format: 'es',
            entryFileNames: '[name]-[hash].js',
            chunkFileNames: 'vendor/[name]-[hash].js',
        });
    } finally {
        await bundle.close();
    }

    const chunks = output.output.filter((item): item is OutputChunk => item.type === 'chunk');
    const assets = output.output.filter((item): item is OutputAsset => item.type === 'asset');

    const buildMetaAsset = assets.find(asset => asset.fileName === '.vite/build-meta.json');
    let vendorMap: Record<string, string> = {};
    let manifest: Record<string, unknown> = {};
    if (buildMetaAsset) {
        const parsed: unknown = JSON.parse(String(buildMetaAsset.source));
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            const meta = parsed as { vendorMap?: unknown; manifest?: unknown };
            if (isStringRecord(meta.vendorMap)) {
                vendorMap = meta.vendorMap;
            }
            if (meta.manifest && typeof meta.manifest === 'object' && !Array.isArray(meta.manifest)) {
                manifest = meta.manifest as Record<string, unknown>;
            }
        }
    }

    return {
        chunks,
        assets,
        vendorMap,
        manifest,
        entryByName: entryName => chunks.find(chunk => chunk.isEntry && chunk.name === entryName),
    };
}

function pkgJson(name: string, exportsMap?: Record<string, string>): string {
    const json: Record<string, unknown> = { name, type: 'module', main: './index.js' };
    if (exportsMap) json.exports = exportsMap;
    return JSON.stringify(json);
}

describe('componentMapPlugin', () => {
    let fixtureRoot: string;

    beforeAll(() => {
        fixtureRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'component-map-plugin-'));
    });

    afterAll(() => {
        fs.rmSync(fixtureRoot, { recursive: true, force: true });
    });

    it('maps a single shared package to its bare specifier and rewrites entry imports', async () => {
        // Two entries share one dep → Rolldown extracts it into a shared,
        // non-facade vendor chunk. The plugin's fallback must still map that
        // chunk to the `single-pkg` specifier (it unambiguously owns it).
        const root = path.join(fixtureRoot, 'single-chunk');
        const result = await build(root, {
            entries: { EntryA: 'EntryA.js', EntryB: 'EntryB.js' },
            files: {
                'EntryA.js': 'import { hello } from \'single-pkg\';\nexport const a = () => hello();\n',
                'EntryB.js': 'import { hello } from \'single-pkg\';\nexport const b = () => hello();\n',
                'node_modules/single-pkg/package.json': pkgJson('single-pkg'),
                'node_modules/single-pkg/index.js': 'export const hello = () => \'hi\';\n',
            },
        });

        expect(Object.keys(result.vendorMap)).toEqual(['single-pkg']);
        expect(result.vendorMap['single-pkg']).toMatch(/^vendor\/[\w.-]+-[\w-]+\.js$/);

        for (const entryName of ['EntryA', 'EntryB']) {
            const entry = result.entryByName(entryName);
            expect(entry, `${entryName} must exist`).toBeDefined();
            expect(entry!.code).toMatch(/from\s*["']single-pkg["']/);
            // No leftover relative reference to the vendor chunk.
            expect(entry!.code).not.toMatch(/from\s*["']\.\/vendor\//);
        }
    });

    it('assigns distinct specifiers to each dynamic-import subpath of a split package', async () => {
        // Dynamic imports always force a per-target facade chunk, which is
        // exactly how the real storefront loads `@shopware-ag/dive/*`.
        // Each facade gets its own subpath-aware specifier.
        const root = path.join(fixtureRoot, 'split-pkg-dynamic');
        const result = await build(root, {
            entries: { Entry: 'Entry.js' },
            files: {
                'Entry.js': [
                    'export async function loadAll() {',
                    '    const [a, b] = await Promise.all([',
                    '        import(\'split-pkg/a\'),',
                    '        import(\'split-pkg/b\'),',
                    '    ]);',
                    '    return a.a() + b.b();',
                    '}',
                    '',
                ].join('\n'),
                'node_modules/split-pkg/package.json': pkgJson('split-pkg', {
                    './a': './a.js',
                    './b': './b.js',
                }),
                'node_modules/split-pkg/a.js': 'export const a = () => \'A\';\n',
                'node_modules/split-pkg/b.js': 'export const b = () => \'B\';\n',
            },
        });

        // Dynamic-import facades are not among the entry's static imports,
        // so vendor-map stays empty: the runtime import map is not involved
        // in the dynamic-import path. Rolldown emits relative chunk URLs
        // directly into the entry's `import(...)` expressions.
        expect(result.vendorMap).toEqual({});

        // Two dedicated facade chunks exist (one per subpath) with the
        // correct facadeModuleIds pointing back into the split package.
        const facadeIds = result.chunks
            .filter(c => !c.isEntry && c.facadeModuleId !== null)
            .map(c => c.facadeModuleId!)
            .sort();
        expect(facadeIds.some(id => id.endsWith('/node_modules/split-pkg/a.js'))).toBe(true);
        expect(facadeIds.some(id => id.endsWith('/node_modules/split-pkg/b.js'))).toBe(true);
    });

    it('adds a static subpath import to vendor-map alongside dynamic subpaths', async () => {
        // A realistic mixed scenario: one subpath is imported statically
        // (needs an import-map entry), the other two only dynamically
        // (don't). The plugin must emit a single specifier → chunk mapping
        // for the static one, and must not collapse the dynamic facades.
        const root = path.join(fixtureRoot, 'split-pkg-mixed');
        const result = await build(root, {
            entries: { EntryA: 'EntryA.js', EntryB: 'EntryB.js' },
            files: {
                'EntryA.js': [
                    'import { a } from \'split-pkg/a\';',
                    'export async function runA() {',
                    '    const b = await import(\'split-pkg/b\');',
                    '    return a() + b.b();',
                    '}',
                    '',
                ].join('\n'),
                'EntryB.js': [
                    'import { a } from \'split-pkg/a\';',
                    'export async function runB() {',
                    '    const c = await import(\'split-pkg/c\');',
                    '    return a() + c.c();',
                    '}',
                    '',
                ].join('\n'),
                'node_modules/split-pkg/package.json': pkgJson('split-pkg', {
                    './a': './a.js',
                    './b': './b.js',
                    './c': './c.js',
                }),
                'node_modules/split-pkg/a.js': 'export const a = () => \'A\';\n',
                'node_modules/split-pkg/b.js': 'export const b = () => \'B\';\n',
                'node_modules/split-pkg/c.js': 'export const c = () => \'C\';\n',
            },
        });

        // The statically-imported subpath is the one that needs the import
        // map — and the ONLY one that should be present.
        expect(Object.keys(result.vendorMap)).toEqual(['split-pkg/a']);
        expect(result.vendorMap['split-pkg/a']).toMatch(/^vendor\/[\w.-]+-[\w-]+\.js$/);

        // Both entries get their static import rewritten to the subpath-
        // aware bare specifier.
        for (const entryName of ['EntryA', 'EntryB']) {
            const entry = result.entryByName(entryName);
            expect(entry, `${entryName} must exist`).toBeDefined();
            expect(entry!.code).toMatch(/from\s*["']split-pkg\/a["']/);
            // Not rewritten to the bare package name.
            expect(entry!.code).not.toMatch(/from\s*["']split-pkg["']/);
        }

        // The dynamic-import facades for b and c still exist and point back
        // at the correct source modules.
        const facadeIds = result.chunks
            .filter(c => !c.isEntry && c.facadeModuleId !== null)
            .map(c => c.facadeModuleId!);
        expect(facadeIds.some(id => id.endsWith('/node_modules/split-pkg/b.js'))).toBe(true);
        expect(facadeIds.some(id => id.endsWith('/node_modules/split-pkg/c.js'))).toBe(true);
    });

    it('skips chunks that combine multiple specifier-owned modules (ambiguous ownership)', async () => {
        // Two entries both import two subpaths statically — Rolldown's
        // default code-splitting combines everything into a single shared
        // chunk. That chunk unambiguously owns *both* `split-pkg/a` and
        // `split-pkg/b`, so neither specifier can claim it. The plugin
        // must skip it; the browser will reach it via its relative URL,
        // which still works because every import site is a sibling of the
        // shared chunk in the served vendor directory.
        const root = path.join(fixtureRoot, 'split-pkg-ambiguous');
        const result = await build(root, {
            entries: { EntryA: 'EntryA.js', EntryB: 'EntryB.js' },
            files: {
                'EntryA.js': [
                    'import { a } from \'split-pkg/a\';',
                    'import { b } from \'split-pkg/b\';',
                    'export const runA = () => a() + b();',
                    '',
                ].join('\n'),
                'EntryB.js': [
                    'import { a } from \'split-pkg/a\';',
                    'import { b } from \'split-pkg/b\';',
                    'export const runB = () => a() + b();',
                    '',
                ].join('\n'),
                'node_modules/split-pkg/package.json': pkgJson('split-pkg', {
                    './a': './a.js',
                    './b': './b.js',
                }),
                'node_modules/split-pkg/a.js': 'export const a = () => \'A\';\n',
                'node_modules/split-pkg/b.js': 'export const b = () => \'B\';\n',
            },
        });

        // Ambiguous ownership → nothing to rewrite, nothing to emit.
        expect(result.vendorMap).toEqual({});

        // Entries keep a relative vendor reference; neither specifier was
        // allowed to claim the shared chunk, which is the whole point of
        // the ambiguity guard.
        for (const entryName of ['EntryA', 'EntryB']) {
            const entry = result.entryByName(entryName);
            expect(entry, `${entryName} must exist`).toBeDefined();
            expect(entry!.code).toMatch(/from\s*["']\.\/vendor\/[\w.-]+-[\w-]+\.js["']/);
            expect(entry!.code).not.toMatch(/from\s*["']split-pkg/);
        }
    });
});
