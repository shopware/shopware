/**
 * @sw-package framework
 */

import * as ts from 'typescript';
import type { AnalysisContext } from './analysis-context';

type PrintContext = Pick<AnalysisContext, 'checker' | 'normalizeTypeText'>;

export const DEFAULT_TYPE_FLAGS = ts.TypeFormatFlags.NoTruncation | ts.TypeFormatFlags.UseAliasDefinedOutsideCurrentScope;

const printer = ts.createPrinter({ removeComments: true });
const printSourceFile = ts.createSourceFile('type-printer.ts', '', ts.ScriptTarget.Latest);

function toNodeBuilderFlags(flags: ts.TypeFormatFlags): ts.NodeBuilderFlags {
    // The overlapping TypeFormatFlags share their bit values with NodeBuilderFlags.
    return (flags & ts.TypeFormatFlags.NodeBuilderFlagsMask) | ts.NodeBuilderFlags.IgnoreErrors;
}

function printNode(node: ts.Node): string {
    return printer.printNode(ts.EmitHint.Unspecified, node, printSourceFile);
}

export function compareText(a: string, b: string): number {
    if (a === b) {
        return 0;
    }

    return a < b ? -1 : 1;
}

/** Union order otherwise follows the checker's internal type ids, which differ between two checkouts. */
const sortUnions: ts.TransformerFactory<ts.Node> = (transformationContext) => {
    const visit = (node: ts.Node): ts.Node => {
        const visited = ts.visitEachChild(node, visit, transformationContext);

        if (!ts.isUnionTypeNode(visited)) {
            return visited;
        }

        const sorted = visited.types
            .map((member) => ({ member, text: printNode(member) }))
            .sort((a, b) => compareText(a.text, b.text))
            .map(({ member }) => member);

        return transformationContext.factory.updateUnionTypeNode(
            visited,
            transformationContext.factory.createNodeArray(sorted),
        );
    };

    return (node) => ts.visitNode(node, visit) ?? node;
};

function printCanonical(node: ts.Node): string {
    const result = ts.transform(node, [sortUnions]);

    try {
        return printNode(result.transformed[0]);
    } finally {
        result.dispose();
    }
}

/**
 * Keeps package paths comparable when they lie outside the admin root,
 * e.g. a fixture root resolving the admin's node_modules.
 */
export function normalizePrintedType(context: PrintContext, text: string): string {
    return context
        .normalizeTypeText(text)
        .replace(/import\((["'])[^"']*\/node_modules\//g, (_match, quote: string) => `import(${quote}node_modules/`);
}

export function printType(
    context: PrintContext,
    type: ts.Type,
    enclosing?: ts.Node,
    flags: ts.TypeFormatFlags = DEFAULT_TYPE_FLAGS,
): string {
    try {
        const node = context.checker.typeToTypeNode(type, enclosing, toNodeBuilderFlags(flags));

        if (node) {
            return normalizePrintedType(context, printCanonical(node));
        }
    } catch {
        // Falls through to the checker's own printer.
    }

    return normalizePrintedType(context, context.checker.typeToString(type, enclosing, flags));
}

export function printSignature(
    context: PrintContext,
    signature: ts.Signature,
    enclosing?: ts.Node,
    flags: ts.TypeFormatFlags = DEFAULT_TYPE_FLAGS,
): string {
    try {
        const node = context.checker.signatureToSignatureDeclaration(
            signature,
            ts.SyntaxKind.CallSignature,
            enclosing,
            toNodeBuilderFlags(flags),
        );

        if (node) {
            return normalizePrintedType(context, printCanonical(node).replace(/;\s*$/, ''));
        }
    } catch {
        // Falls through to the checker's own printer.
    }

    return normalizePrintedType(context, context.checker.signatureToString(signature, enclosing, flags));
}
