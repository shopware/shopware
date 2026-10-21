/**
 * @sw-package framework
 */

import * as path from 'node:path';
import * as ts from 'typescript';
import type { AnalysisContext } from '../analysis-context';
import type { ExtractionResult, ShopwareObjectNode, SnapshotDiagnostic } from '../types';
import { ShopwareObjectWalker } from './walker';

export const ENTRY_FILE = 'src/core/shopware.ts';
const INSTANCE_EXPORT = 'ShopwareInstance';
const CLASS_EXPORT = 'ShopwareClass';

function emptyRoot(): ShopwareObjectNode {
    return {
        path: 'Shopware',
        name: 'Shopware',
        kind: 'object',
        signature: '{}',
        callSignatures: [],
        typeSource: 'unknown',
        truncated: false,
        children: [],
        visibility: 'public',
        deprecation: null,
        location: null,
    };
}

function failure(message: string): ExtractionResult<ShopwareObjectNode> {
    return { result: emptyRoot(), diagnostics: [{ file: ENTRY_FILE, message }] };
}

function resolveExport(checker: ts.TypeChecker, exports: ts.Symbol[], name: string): ts.Symbol | undefined {
    const symbol = exports.find((candidate) => candidate.name === name);

    return symbol && symbol.flags & ts.SymbolFlags.Alias ? checker.getAliasedSymbol(symbol) : symbol;
}

function extract(context: AnalysisContext): ExtractionResult<ShopwareObjectNode> {
    const { checker, program } = context;
    const sourceFile = program.getSourceFile(path.join(context.adminRoot, ENTRY_FILE));
    const moduleSymbol = sourceFile && checker.getSymbolAtLocation(sourceFile);

    if (!sourceFile || !moduleSymbol) {
        return failure(`${ENTRY_FILE} is missing, the Shopware object cannot be extracted.`);
    }

    const moduleExports = checker.getExportsOfModule(moduleSymbol);
    const instance = resolveExport(checker, moduleExports, INSTANCE_EXPORT);

    if (!instance) {
        return failure(`${ENTRY_FILE} does not export ${INSTANCE_EXPORT}, the Shopware object cannot be extracted.`);
    }

    const classSymbol = resolveExport(checker, moduleExports, CLASS_EXPORT);
    const rootDeclaration = classSymbol?.valueDeclaration ?? instance.valueDeclaration;
    const walker = new ShopwareObjectWalker(context);
    const result = walker.walkRoot(checker.getTypeOfSymbol(instance), rootDeclaration);
    const diagnostics: SnapshotDiagnostic[] = walker.diagnostics;

    return { result, diagnostics };
}

/** Runs synchronously before its first await, so it never interleaves with other users of the checker. */
export function extractShopwareObject(context: AnalysisContext): Promise<ExtractionResult<ShopwareObjectNode>> {
    return Promise.resolve(extract(context));
}
