/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import type * as ts from 'typescript';
import { createAnalysisContext, type AnalysisContext } from './analysis-context';
import { printSignature, printType } from './type-printer';

const SOURCE = `
export type Code = 'zeta' | 'alpha';
export const pair: Map<'b' | 'a', Array<'z' | 'y' | null>> = new Map();
export function choose(value: 'q' | 'p' | number, fallback?: Code): 'd' | 'c' {
    return value === 'q' && fallback === undefined ? 'd' : 'c';
}
export const huge: { first: 'b' | 'a' } = { first: 'a' };
`;

describe('type printer', () => {
    let tmpDir: string;
    let context: AnalysisContext;

    const exportType = (name: string): ts.Type => {
        const sourceFile = context.program.getSourceFile(path.join(tmpDir, 'src/types.ts'));
        const moduleSymbol = sourceFile && context.checker.getSymbolAtLocation(sourceFile);
        const symbol = moduleSymbol && context.checker.getExportsOfModule(moduleSymbol).find((s) => s.name === name);

        if (!symbol) {
            throw new Error(`Export ${name} not found`);
        }

        return context.checker.getTypeOfSymbol(symbol);
    };

    beforeAll(() => {
        tmpDir = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-type-printer-')));
        fs.mkdirSync(path.join(tmpDir, 'src'));
        fs.writeFileSync(path.join(tmpDir, 'src/types.ts'), SOURCE);
        fs.writeFileSync(
            path.join(tmpDir, 'tsconfig.json'),
            JSON.stringify({ compilerOptions: { strict: true, target: 'ES2022', types: [] }, include: ['./src/**/*'] }),
        );
        context = createAnalysisContext(tmpDir);
    }, 60_000);

    afterAll(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    it('sorts unions nested in type arguments and object members', () => {
        expect(printType(context, exportType('pair'))).toBe('Map<"a" | "b", ("y" | "z" | null)[]>');
        expect(printType(context, exportType('huge'))).toBe('{ first: "a" | "b"; }');
    });

    it('sorts unions in parameters and return types of signatures', () => {
        const [signature] = exportType('choose').getCallSignatures();

        expect(printSignature(context, signature)).toBe(
            '(value: "p" | "q" | number, fallback?: Code | undefined): "c" | "d"',
        );
        expect(printType(context, signature.getReturnType())).toBe('"c" | "d"');
    });
});
