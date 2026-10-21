/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { createAnalysisContext, type AnalysisContext } from '../analysis-context';
import type { ShopwareObjectNode, SnapshotDiagnostic } from '../types';
import { extractShopwareObject } from '.';

const FIXTURE_ROOT = path.join(__dirname, '__fixtures__', 'admin-root');
const PROGRAM_TIMEOUT = 60_000;

function flatten(node: ShopwareObjectNode): ShopwareObjectNode[] {
    return [
        node,
        ...node.children.flatMap(flatten),
    ];
}

function findNode(root: ShopwareObjectNode, nodePath: string): ShopwareObjectNode {
    const node = flatten(root).find((candidate) => candidate.path === nodePath);

    if (!node) {
        throw new Error(`No node ${nodePath}`);
    }

    return node;
}

function childPaths(root: ShopwareObjectNode, nodePath: string): string[] {
    return findNode(root, nodePath).children.map((child) => child.path);
}

describe('extractShopwareObject', () => {
    let context: AnalysisContext;
    let root: ShopwareObjectNode;
    let diagnostics: SnapshotDiagnostic[];

    beforeAll(async () => {
        context = createAnalysisContext(FIXTURE_ROOT);
        ({ result: root, diagnostics } = await extractShopwareObject(context));
    }, PROGRAM_TIMEOUT);

    it('starts at the Shopware instance and keeps the declaration order', () => {
        expect(diagnostics).toEqual([]);
        expect(root).toMatchObject({
            path: 'Shopware',
            name: 'Shopware',
            kind: 'object',
            signature: '{ Registry, Data, Defaults, … }',
            typeSource: 'typescript',
            location: { file: 'src/core/shopware.ts', line: 19 },
        });
        expect(childPaths(root, 'Shopware')).toEqual([
            'Shopware.Registry',
            'Shopware.Data',
            'Shopware.Defaults',
            'Shopware.Vue',
            'Shopware.Legacy',
            'Shopware.lookup',
            'Shopware.Labs',
            'Shopware._private',
            'Shopware.Tree',
            'Shopware.Deep',
            'Shopware.Snippet',
            'Shopware.Version',
        ]);
    });

    it('follows shorthand assignments to the declaration in the other module', () => {
        expect(findNode(root, 'Shopware.Registry.register')).toMatchObject({
            kind: 'function',
            typeSource: 'typescript',
            visibility: 'public',
            location: { file: 'src/core/registry.ts', line: 7 },
            signature: '(name: string, priority?: number | undefined): boolean',
            callSignatures: [
                {
                    parameters: [
                        { name: 'name', type: 'string', optional: false, rest: false },
                        { name: 'priority', type: 'number | undefined', optional: true, rest: false },
                    ],
                    returnType: 'boolean',
                },
            ],
        });
        expect(findNode(root, 'Shopware.Registry.format')).toMatchObject({
            typeSource: 'jsdoc',
            location: { file: 'src/core/format.js', line: 5 },
            signature: '(value: string): string',
        });
    });

    it('takes visibility only from the exposure path but deprecation from the alias target', () => {
        expect(findNode(root, 'Shopware.Registry.internalHelper')).toMatchObject({
            visibility: 'public',
            location: { file: 'src/core/legacy.ts', line: 4 },
        });
        expect(findNode(root, 'Shopware.Registry.oldFormat')).toMatchObject({
            visibility: 'public',
            deprecation: { version: 'v6.9.0', message: 'Use format instead.' },
        });
    });

    it('lists every overload without the implementation signature', () => {
        const pick = findNode(root, 'Shopware.Registry.pick');

        expect(pick.signature).toBe('(key: string): string\n(key: string, fallback: number): number');
        expect(pick.callSignatures).toEqual([
            {
                parameters: [{ name: 'key', type: 'string', optional: false, rest: false }],
                returnType: 'string',
            },
            {
                parameters: [
                    { name: 'key', type: 'string', optional: false, rest: false },
                    { name: 'fallback', type: 'number', optional: false, rest: false },
                ],
                returnType: 'number',
            },
        ]);
        expect(findNode(root, 'Shopware.Legacy.run').callSignatures[0].parameters).toEqual([
            { name: 'args', type: 'string[]', optional: false, rest: true },
        ]);
    });

    it('describes classes with static members, instance members and their type sources', () => {
        expect(findNode(root, 'Shopware.Data.ChangesetGenerator')).toMatchObject({
            kind: 'class',
            signature: 'class ChangesetGenerator',
            typeSource: 'inferred',
            truncated: false,
        });
        expect(childPaths(root, 'Shopware.Data.ChangesetGenerator')).toEqual([
            'Shopware.Data.ChangesetGenerator.create',
            'Shopware.Data.ChangesetGenerator#generate',
            'Shopware.Data.ChangesetGenerator#recursion',
            'Shopware.Data.ChangesetGenerator#untyped',
        ]);
        expect(findNode(root, 'Shopware.Data.ChangesetGenerator#generate')).toMatchObject({
            kind: 'method',
            typeSource: 'jsdoc',
            visibility: 'public',
            callSignatures: [
                {
                    parameters: [{ name: 'entity', type: 'object', optional: false, rest: false }],
                    returnType: '{ changes: object; deletionQueue: any[]; }',
                },
            ],
        });
        expect(findNode(root, 'Shopware.Data.ChangesetGenerator#recursion').visibility).toBe('private');
        expect(findNode(root, 'Shopware.Data.ChangesetGenerator#untyped').typeSource).toBe('inferred');
    });

    it('leaves out members inherited from external base classes', () => {
        expect(childPaths(root, 'Shopware.Data.EntityList')).toEqual([
            'Shopware.Data.EntityList#first',
        ]);
    });

    it('marks literal-valued members as constants and keeps arrays as leaves', () => {
        expect(findNode(root, 'Shopware.Defaults.systemLanguageId')).toMatchObject({
            kind: 'constant',
            signature: 'string',
        });
        expect(findNode(root, 'Shopware.Defaults.languageIds')).toMatchObject({
            kind: 'constant',
            signature: 'string[]',
            children: [],
            truncated: false,
        });
        expect(findNode(root, 'Shopware.Defaults.mutableLimit').kind).toBe('property');
    });

    it('turns members typed from a package into truncated leaves', () => {
        expect(findNode(root, 'Shopware.Vue')).toMatchObject({
            kind: 'object',
            truncated: true,
            children: [],
            signature: 'typeof import("node_modules/vue/dist/vue")',
            location: { file: 'src/core/shopware.ts', line: 40 },
        });
    });

    it('reads deprecations with and without a version tag', () => {
        expect(findNode(root, 'Shopware.Legacy').deprecation).toEqual({
            version: 'v6.8.0',
            message: 'Will be removed, use Registry instead.',
        });
        expect(findNode(root, 'Shopware.Legacy.run').deprecation).toBeNull();
        expect(findNode(root, 'Shopware.lookup').deprecation).toEqual({
            version: null,
            message: 'Use Registry.get instead.',
        });
    });

    it('inherits non-public visibility from ancestors', () => {
        expect(findNode(root, 'Shopware._private').visibility).toBe('private');
        expect(findNode(root, 'Shopware._private.Helpers.noop').visibility).toBe('private');
        expect(findNode(root, 'Shopware.Labs').visibility).toBe('experimental');
        expect(findNode(root, 'Shopware.Labs.stable').visibility).toBe('experimental');
        expect(findNode(root, 'Shopware.Labs.preview').visibility).toBe('private');
        expect(findNode(root, 'Shopware.Registry.get').visibility).toBe('public');
    });

    it('recurses into getters that return objects', () => {
        expect(findNode(root, 'Shopware.Snippet')).toMatchObject({
            kind: 'getter',
            signature: '{ t, locale }',
        });
        expect(findNode(root, 'Shopware.Snippet.t')).toMatchObject({
            kind: 'function',
            signature: '(key: string): string',
        });
        expect(findNode(root, 'Shopware.Version')).toMatchObject({
            kind: 'getter',
            signature: 'string',
            children: [],
        });
    });

    it('stops at cycles and at the depth limit', () => {
        expect(findNode(root, 'Shopware.Tree.parent')).toMatchObject({
            truncated: true,
            children: [],
            signature: '{ name, parent }',
        });
        expect(findNode(root, 'Shopware.Deep.a.b.c.d.e.f.g')).toMatchObject({
            truncated: true,
            children: [],
            signature: '{ h }',
        });
        expect(
            flatten(root)
                .filter(({ truncated }) => truncated)
                .map(({ path: nodePath }) => nodePath),
        ).toEqual([
            'Shopware.Vue',
            'Shopware.Tree.parent',
            'Shopware.Deep.a.b.c.d.e.f.g',
        ]);
    });

    it('prints no absolute paths', () => {
        const json = JSON.stringify(root);

        expect(json).not.toContain(FIXTURE_ROOT);
        expect(json).not.toMatch(/import\(\\?"\//);
    });

    it(
        'produces identical output for repeated runs and for a fresh program',
        async () => {
            const again = await extractShopwareObject(context);
            const fresh = await extractShopwareObject(createAnalysisContext(FIXTURE_ROOT));

            expect(JSON.stringify(again.result)).toBe(JSON.stringify(root));
            expect(JSON.stringify(fresh.result)).toBe(JSON.stringify(root));
        },
        PROGRAM_TIMEOUT,
    );
});

describe('extractShopwareObject without a usable entry file', () => {
    let tmpDir: string;

    const createRoot = (files: Record<string, string>): string => {
        fs.cpSync(path.join(FIXTURE_ROOT, 'tsconfig.json'), path.join(tmpDir, 'tsconfig.json'));

        Object.entries(files).forEach(([fileName, content]) => {
            fs.mkdirSync(path.dirname(path.join(tmpDir, fileName)), { recursive: true });
            fs.writeFileSync(path.join(tmpDir, fileName), content);
        });

        return tmpDir;
    };

    beforeEach(() => {
        tmpDir = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-shopware-object-')));
    });

    afterEach(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    it(
        'returns the empty root and a diagnostic when the entry file is missing',
        async () => {
            const adminRoot = createRoot({ 'src/app/main.ts': 'export const main = 1;\n' });
            const extraction = await extractShopwareObject(createAnalysisContext(adminRoot));

            expect(extraction.result).toMatchObject({ path: 'Shopware', kind: 'object', children: [] });
            expect(extraction.diagnostics).toEqual([
                {
                    file: 'src/core/shopware.ts',
                    message: 'src/core/shopware.ts is missing, the Shopware object cannot be extracted.',
                },
            ]);
        },
        PROGRAM_TIMEOUT,
    );

    it(
        'returns the empty root and a diagnostic when the instance is not exported',
        async () => {
            const adminRoot = createRoot({ 'src/core/shopware.ts': 'export const Shopware = { version: 1 };\n' });
            const extraction = await extractShopwareObject(createAnalysisContext(adminRoot));

            expect(extraction.result.children).toEqual([]);
            expect(extraction.diagnostics).toEqual([
                {
                    file: 'src/core/shopware.ts',
                    message:
                        'src/core/shopware.ts does not export ShopwareInstance, the Shopware object cannot be extracted.',
                },
            ]);
        },
        PROGRAM_TIMEOUT,
    );
});
