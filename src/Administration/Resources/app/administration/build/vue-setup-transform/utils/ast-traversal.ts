/**
 * @sw-package framework
 */

/**
 * Small shared depth-first traversal primitives over Babel nodes.
 *
 * Several analyzer passes walk the same AST shape by enumerating a node's children. These helpers own
 * the child-enumeration boilerplate (flatten arrays, skip position/comment keys) so each pass only has
 * to express its own visitor.
 */

import type {
    ArrowFunctionExpression,
    ClassMethod,
    ClassPrivateMethod,
    FunctionDeclaration,
    FunctionExpression,
    Node as BabelNode,
    ObjectMethod,
} from '@babel/types';
import { isBabelNodeLike } from './babel-patterns';

type FunctionLikeNode =
    | FunctionDeclaration
    | FunctionExpression
    | ArrowFunctionExpression
    | ObjectMethod
    | ClassMethod
    | ClassPrivateMethod;

/** Object keys that never hold traversable child nodes (source positions and comments). */
const NON_NODE_KEYS = [
    'loc',
    'range',
    'start',
    'end',
    'leadingComments',
    'trailingComments',
    'innerComments',
];

type ChildKeyFilter = (key: string) => boolean;

/** A child Babel node paired with the field name it was found under. */
type ChildBabelEntry = {
    node: BabelNode;
    key: string;
};

/**
 * The node kinds that introduce a new function scope.
 *
 * `TSDeclareFunction` is intentionally excluded: it is an ambient signature with no body, so it can
 * neither hold a lexical scope the rename walk must track nor contain an `await` the top-level-await
 * check cares about. The one caller that needs it (that await check) can special-case it if ever
 * required; today none does.
 */
function isFunctionLikeNode(node: BabelNode): node is FunctionLikeNode {
    return (
        node.type === 'FunctionDeclaration' ||
        node.type === 'FunctionExpression' ||
        node.type === 'ArrowFunctionExpression' ||
        node.type === 'ObjectMethod' ||
        node.type === 'ClassMethod' ||
        node.type === 'ClassPrivateMethod'
    );
}

/** Type-position fields, skipped by walks that only care about value (runtime) positions. */
function isTypeKey(key: string): boolean {
    return [
        'typeAnnotation',
        'typeParameters',
        'typeArguments',
        'returnType',
    ].includes(key);
}

/**
 * Returns the direct child Babel nodes of a node paired with their field key, flattening arrays.
 *
 * `skipKey` drops extra fields (for example type positions) on top of the always-skipped
 * position/comment keys.
 */
function childBabelEntries(node: BabelNode, skipKey?: ChildKeyFilter): ChildBabelEntry[] {
    const entries: ChildBabelEntry[] = [];

    for (const [
        key,
        value,
    ] of Object.entries(node as unknown as Record<string, unknown>)) {
        if (NON_NODE_KEYS.includes(key) || skipKey?.(key)) {
            continue;
        }

        if (Array.isArray(value)) {
            value.forEach((child) => {
                if (isBabelNodeLike(child)) {
                    entries.push({
                        node: child,
                        key,
                    });
                }
            });
            continue;
        }

        if (isBabelNodeLike(value)) {
            entries.push({
                node: value,
                key,
            });
        }
    }

    return entries;
}

/**
 * Returns the direct child Babel nodes of a node, flattening array-valued fields.
 */
function childBabelNodes(node: BabelNode, skipKey?: ChildKeyFilter): BabelNode[] {
    return childBabelEntries(node, skipKey).map((entry) => entry.node);
}

/**
 * @private
 */
export {
    type ChildBabelEntry,
    type ChildKeyFilter,
    type FunctionLikeNode,
    childBabelEntries,
    childBabelNodes,
    isFunctionLikeNode,
    isTypeKey,
};
