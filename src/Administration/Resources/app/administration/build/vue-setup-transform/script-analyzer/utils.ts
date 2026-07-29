/**
 * @sw-package framework
 */

/**
 * Shared Babel parsing and traversal helpers for the Shopware setup analyzer.
 *
 * The helpers keep source ranges in the script-local coordinate space; callers add the SFC block
 * offset only when producing diagnostics or original-source chunks.
 */

import { parse, type ParserPlugin } from '@babel/parser';
import type { File as BabelFile, Node as BabelNode } from '@babel/types';
import { ShopwareSetupTransformError, type ShopwareSetupErrorPosition } from '../utils/transform-error';
import { childBabelNodes } from '../utils/ast-traversal';
import type { SourceRange } from '../utils/source-range';

type AstVisitor = (node: BabelNode, ancestors: BabelNode[]) => void;

/**
 * Converts Babel source ranges into the transform's compact range shape.
 *
 * The result is **script-local**: it indexes into the `<script setup>` content, not the whole SFC. Use
 * `absoluteRange()` to move a range into SFC coordinates for a diagnostic.
 */
function getNodeRange(node: BabelNode): SourceRange {
    if (typeof node.start !== 'number' || typeof node.end !== 'number') {
        throw new ShopwareSetupTransformError('Missing source range metadata while transforming Shopware setup.');
    }

    return {
        start: node.start,
        end: node.end,
    };
}

/**
 * Moves a node's full range from script-local into absolute SFC coordinates for a diagnostic.
 *
 * The transform juggles two coordinate spaces: analyzer ranges are script-local (they index into the
 * script block's content), while `ShopwareSetupTransformError` positions are absolute SFC offsets. This
 * is the one place that conversion happens, so call sites no longer open-code `offset + range.start`
 * and cannot silently forget it. Passing the result to the error carries both the start (for build/CLI
 * adapters) and the end (so ESLint underlines the whole offending token).
 */
function absoluteRange(node: BabelNode, blockOffset: number): ShopwareSetupErrorPosition {
    const range = getNodeRange(node);

    return {
        index: blockOffset + range.start,
        endIndex: blockOffset + range.end,
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
 * Small AST walker used to avoid taking a heavier traversal dependency.
 *
 * Child enumeration is delegated to `childBabelNodes`, so this and the other analyzer walks share one
 * definition of "which fields hold traversable children".
 */
function walk(node: BabelNode | null | undefined, visitor: AstVisitor, ancestors: BabelNode[] = []): void {
    if (!node || typeof node.type !== 'string') {
        return;
    }

    visitor(node, ancestors);

    const childAncestors = [
        ...ancestors,
        node,
    ];

    childBabelNodes(node).forEach((child) => walk(child, visitor, childAncestors));
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

/**
 * @private
 */
export { type SourceRange, absoluteRange, getNodeRange, parseScript, unwrapTransparentMacroExpression, walk };
