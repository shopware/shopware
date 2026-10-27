/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import * as ts from 'typescript';
import { createAnalysisContext, type AnalysisContext } from './analysis-context';

const FIXTURE_ROOT = path.join(__dirname, '..', '__fixtures__', 'admin-root');

function exportTypeText(context: AnalysisContext, file: string, exportName: string): string {
    const sourceFile = context.program.getSourceFile(path.join(context.adminRoot, file));
    const moduleSymbol = sourceFile && context.checker.getSymbolAtLocation(sourceFile);
    const symbol = moduleSymbol && context.checker.getExportsOfModule(moduleSymbol).find(({ name }) => name === exportName);

    if (!symbol) {
        throw new Error(`Export ${exportName} not found in ${file}`);
    }

    return context.normalizeTypeText(
        context.checker.typeToString(
            context.checker.getTypeOfSymbol(symbol),
            undefined,
            ts.TypeFormatFlags.UseFullyQualifiedType | ts.TypeFormatFlags.NoTruncation,
        ),
    );
}

describe('createAnalysisContext', () => {
    let tmpDir: string;

    const copyFixture = (name: string): string => {
        const root = path.join(tmpDir, name);
        fs.cpSync(FIXTURE_ROOT, root, { recursive: true });

        return root;
    };

    beforeEach(() => {
        tmpDir = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-context-')));
    });

    afterEach(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    it('builds one program from the TS and JS sources plus the declaration files, without specs and mocks', () => {
        const context = createAnalysisContext(FIXTURE_ROOT);
        const projectFiles = context.program
            .getSourceFiles()
            .map(({ fileName }) => context.toRelativePath(fileName))
            .filter((fileName) => !fileName.startsWith('/') && !fileName.startsWith('node_modules/'))
            .sort();

        expect(context.adminRoot).toBe(FIXTURE_ROOT);
        expect(projectFiles).toEqual([
            'src/core/factory.ts',
            'src/core/format.js',
            'src/core/shopware.ts',
            'types/global.d.ts',
        ]);
        expect(exportTypeText(context, 'src/core/format.js', 'format')).toBe('(value: string) => string');
    });

    it('picks up a changed file when rebuilding from a previous context and reuses unchanged files', () => {
        const root = copyFixture('worktree');
        const first = createAnalysisContext(root);

        expect(first.checker.typeToString(getMethodType(first, 'get'))).toBe('(name: string) => string');

        fs.writeFileSync(
            path.join(root, 'src/core/factory.ts'),
            'export class Registry {\n    get(name: string): number {\n        return name.length;\n    }\n}\n',
        );
        const second = createAnalysisContext(root, { previous: first });

        expect(second.checker.typeToString(getMethodType(second, 'get'))).toBe('(name: string) => number');
        expect(second.program.getSourceFile(path.join(root, 'src/core/shopware.ts'))).toBe(
            first.program.getSourceFile(path.join(root, 'src/core/shopware.ts')),
        );
    });

    it('prints identical type text for the same code in two roots sharing a symlinked node_modules', () => {
        const sharedNodeModules = path.join(tmpDir, 'node_modules');
        fs.mkdirSync(path.join(sharedNodeModules, 'fake-lib'), { recursive: true });
        fs.writeFileSync(path.join(sharedNodeModules, 'fake-lib', 'index.d.ts'), 'export interface Options { a: string }\n');

        const texts = [
            'base',
            'head',
        ].map((name) => {
            const root = copyFixture(name);
            fs.symlinkSync(sharedNodeModules, path.join(root, 'node_modules'), 'dir');
            fs.writeFileSync(
                path.join(root, 'src/core/configure.ts'),
                "import type { Options } from 'fake-lib';\nimport { Registry } from './factory';\n\n" +
                    'export const configure = (options: Options): Registry => new Registry();\n',
            );

            return exportTypeText(createAnalysisContext(root), 'src/core/configure.ts', 'configure');
        });

        expect(texts[0]).toBe(
            '(options: import("node_modules/fake-lib/index").Options) => import("src/core/factory").Registry',
        );
        expect(texts[1]).toBe(texts[0]);
    });

    it('normalizes whitespace to a single line', () => {
        const context = createAnalysisContext(FIXTURE_ROOT);

        expect(context.normalizeTypeText('{\n    a: string;\n    b: number;\n}')).toBe('{ a: string; b: number; }');
    });
});

function getMethodType(context: AnalysisContext, methodName: string): ts.Type {
    const sourceFile = context.program.getSourceFile(path.join(context.adminRoot, 'src/core/factory.ts'));
    const registry = sourceFile?.statements.find(ts.isClassDeclaration);
    const method = registry?.members.find(
        (member) => ts.isMethodDeclaration(member) && member.name.getText() === methodName,
    );

    if (!method) {
        throw new Error(`Method ${methodName} not found`);
    }

    return context.checker.getTypeAtLocation(method);
}
