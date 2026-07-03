/**
 * @sw-package framework
 */

import { parse, type ParserPlugin } from '@babel/parser';
import type { File as BabelFile, Node as BabelNode } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';

type SourceRange = {
    start: number;
    end: number;
};

type AstVisitor = (node: BabelNode, ancestors: BabelNode[]) => void;

function isBabelNodeLike(value: unknown): value is BabelNode {
    return Boolean(value && typeof value === 'object' && 'type' in value && typeof value.type === 'string');
}

/**
 * Converts Babel source ranges into the transform's compact range shape.
 */
function getNodeRange(node: BabelNode, scriptOffset: number): SourceRange {
    if (typeof node.start !== 'number' || typeof node.end !== 'number') {
        throw new ShopwareSetupTransformError(
            'Missing source range metadata while transforming Shopware setup.',
            scriptOffset,
        );
    }

    return {
        start: node.start,
        end: node.end,
    };
}

/**
 * Parses user setup code with the plugins required by the declared script language.
 */
function parseScript(script: string, lang: string, scriptOffset: number): BabelFile {
    const plugins: ParserPlugin[] = [
        'importMeta',
    ];

    if (lang === 'ts' || lang === 'tsx') {
        plugins.push('typescript');
    }

    if (lang === 'jsx' || lang === 'tsx') {
        plugins.push('jsx');
    }

    try {
        return parse(script, {
            sourceType: 'module',
            plugins,
            errorRecovery: false,
            allowReturnOutsideFunction: false,
            ranges: true,
        });
    } catch (error: unknown) {
        const parserError = error as { pos?: unknown; message?: unknown };
        const offset = typeof parserError.pos === 'number' ? scriptOffset + parserError.pos : scriptOffset;
        const message = typeof parserError.message === 'string' ? parserError.message : String(error);
        throw new ShopwareSetupTransformError(`Unable to parse Shopware setup script: ${message}`, offset);
    }
}

/**
 * Identifies scopes where `await` is no longer top-level for this transform.
 */
function isFunctionNode(node: BabelNode): boolean {
    return [
        'FunctionDeclaration',
        'FunctionExpression',
        'ArrowFunctionExpression',
        'ObjectMethod',
        'ClassMethod',
        'ClassPrivateMethod',
        'TSDeclareFunction',
    ].includes(node.type);
}

/**
 * Small AST walker used to avoid taking a heavier traversal dependency.
 */
function walk(node: BabelNode | null | undefined, visitor: AstVisitor, ancestors: BabelNode[] = []): void {
    if (!node || typeof node.type !== 'string') {
        return;
    }

    visitor(node, ancestors);

    Object.entries(node as unknown as Record<string, unknown>).forEach(
        ([
            key,
            value,
        ]) => {
            if (
                key === 'loc' ||
                key === 'range' ||
                key === 'leadingComments' ||
                key === 'trailingComments' ||
                key === 'innerComments'
            ) {
                return;
            }

            if (Array.isArray(value)) {
                value.forEach((child) => {
                    if (isBabelNodeLike(child)) {
                        walk(child, visitor, [
                            ...ancestors,
                            node,
                        ]);
                    }
                });
                return;
            }

            if (isBabelNodeLike(value)) {
                walk(value, visitor, [
                    ...ancestors,
                    node,
                ]);
            }
        },
    );
}

/**
 * Checks whether `inner` is fully covered by `outer`.
 */
function containsRange(outer: SourceRange, inner: SourceRange): boolean {
    return outer.start <= inner.start && inner.end <= outer.end;
}

/**
 * Returns the expression Vue treats as the compiler macro call through transparent TypeScript wrappers.
 * Example: `defineProps<Props>() as Props` is collected as the inner `defineProps<Props>()` call while the
 * replacement range still preserves `as Props` around the generated setup input.
 */
function unwrapTransparentMacroExpression(node: BabelNode | null | undefined): BabelNode | null | undefined {
    if (
        node?.type === 'TSAsExpression' ||
        node?.type === 'TSSatisfiesExpression' ||
        node?.type === 'TSTypeAssertion' ||
        node?.type === 'TSNonNullExpression' ||
        node?.type === 'ParenthesizedExpression'
    ) {
        return unwrapTransparentMacroExpression((node as { expression: BabelNode }).expression);
    }

    return node;
}

export {
    type AstVisitor,
    type SourceRange,
    containsRange,
    getNodeRange,
    isBabelNodeLike,
    isFunctionNode,
    parseScript,
    unwrapTransparentMacroExpression,
    walk,
};
