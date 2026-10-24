/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import type * as ts from 'typescript';
import { createAnalysisContext, type AnalysisContext } from './analysis-context';
import { digestType, type TypeDigest } from './type-hash';

const SOURCE = `
export interface Named { value: string }

type Deep<T> = T extends (...args: never[]) => unknown ? T : T extends object ? { [K in keyof T]: Deep<T[K]> } : T;

export interface TreeA { label: string; parent: TreeA | null; children: TreeA[]; }
export interface TreeB { children: TreeB[]; label: string; parent: TreeB | null; }

export declare const order: Deep<{
    aCallback: (input: 'b' | 'a') => Named;
    size: 'small' | 'large' | 'medium';
    level1: { level2: { level3: { value: number; flag?: boolean; code: 'first' } } };
    tree: TreeA;
}>;
export declare const treeA: Deep<TreeA>;
export declare const treeB: Deep<TreeB>;
`;

describe('type digest', () => {
    let tmpDir: string;

    const createRoot = (name: string, files: Record<string, string>): AnalysisContext => {
        const root = path.join(tmpDir, name);

        fs.mkdirSync(path.join(root, 'src'), { recursive: true });
        fs.writeFileSync(
            path.join(root, 'tsconfig.json'),
            JSON.stringify({ compilerOptions: { strict: true, target: 'ES2022', types: [] }, include: ['./src/**/*'] }),
        );

        for (const [file, content] of Object.entries(files)) {
            fs.writeFileSync(path.join(root, 'src', file), content);
        }

        return createAnalysisContext(root);
    };

    const exportType = (context: AnalysisContext, file: string, name: string): ts.Type => {
        const sourceFile = context.program.getSourceFile(path.join(context.adminRoot, 'src', file));
        const moduleSymbol = sourceFile && context.checker.getSymbolAtLocation(sourceFile);
        const symbol = moduleSymbol && context.checker.getExportsOfModule(moduleSymbol).find((s) => s.name === name);

        if (!symbol) {
            throw new Error(`Export ${name} not found`);
        }

        return context.checker.getTypeOfSymbol(symbol);
    };

    const digest = (name: string, source = SOURCE, exportName = 'order'): TypeDigest => {
        const context = createRoot(name, { 'types.ts': source });

        return digestType(context, exportType(context, 'types.ts', exportName));
    };

    let original: TypeDigest;

    beforeAll(() => {
        tmpDir = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-type-hash-')));
        original = digest('original');
    }, 60_000);

    afterAll(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    it('renders a canonical text with sorted unions and members and named types by reference', () => {
        expect(original.display(140)).toBe(
            '{ aCallback: (input: "a" | "b") => import("src/types").Named; level1: { level2: { level3: { code: "first"; flag?: boolean; value: number; };',
        );
        expect(original.hash).toMatch(/^[0-9a-f]{40}$/);
    });

    it('measures the full rendering of acyclic types', () => {
        const acyclic = digest('acyclic', SOURCE.replace('tree: TreeA;', ''));

        expect(acyclic).toHaveLength(acyclic.display(Number.MAX_SAFE_INTEGER).length);
    });

    it('does not depend on the order in which the checker created the types', () => {
        const context = createRoot('perturbed', {
            'aaa.ts': "export declare const noise: 'medium' | 'large' | 'small' | 'first' | 'b' | 'a';\n",
            'types.ts': SOURCE,
        });
        const originalContext = createRoot('unperturbed', { 'types.ts': SOURCE });
        const sizeText = (analysis: AnalysisContext): string => {
            const size = analysis.checker.getPropertyOfType(exportType(analysis, 'types.ts', 'order'), 'size');

            return analysis.checker.typeToString(analysis.checker.getTypeOfSymbol(size!));
        };

        exportType(context, 'aaa.ts', 'noise');

        expect(sizeText(context)).not.toBe(sizeText(originalContext));

        const perturbed = digestType(context, exportType(context, 'types.ts', 'order'));

        expect(perturbed.hash).toBe(original.hash);
        expect(perturbed.display(300)).toBe(original.display(300));
        expect(perturbed).toHaveLength(original.length);
    }, 60_000);

    it('is identical for the same code in another root', () => {
        const other = digest('other-root');

        expect(other.hash).toBe(original.hash);
        expect(other.display(300)).toBe(original.display(300));
    }, 60_000);

    it('ignores the declaration order of properties', () => {
        const reordered = digest(
            'reordered',
            SOURCE.replace(
                "{ value: number; flag?: boolean; code: 'first' }",
                "{ code: 'first'; value: number; flag?: boolean }",
            ),
        );

        expect(reordered.hash).toBe(original.hash);
    }, 60_000);

    it.each([
        ['a property type three levels down', 'value: number;', 'value: string;'],
        ['an optional flag', 'flag?: boolean;', 'flag: boolean;'],
        ['a literal value', "code: 'first'", "code: 'second'"],
        ['a type in a cycle', 'label: string; parent: TreeA', 'label: number; parent: TreeA'],
    ])(
        'changes the hash for a change of %s',
        (_name, search, replacement) => {
            const changed = digest(`changed-${search.replace(/\W/g, '')}`, SOURCE.replace(search, replacement));

            expect(changed.hash).not.toBe(original.hash);
        },
        60_000,
    );

    it('digests cyclic types by structure and marks back references', () => {
        const context = createRoot('cycles', { 'types.ts': SOURCE });
        const treeA = digestType(context, exportType(context, 'types.ts', 'treeA'));
        const treeB = digestType(context, exportType(context, 'types.ts', 'treeB'));

        expect(treeA.hash).toBe(treeB.hash);
        expect(treeA.display(300)).toBe(treeB.display(300));
        expect(treeA.display(300)).toBe('{ children: <cycle ^2>[]; label: string; parent: null | <cycle ^2>; }');
        expect(treeA.length).toBeGreaterThan(0);
    }, 60_000);
});
