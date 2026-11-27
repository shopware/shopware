/**
 * @sw-package framework
 *
 * Finds the object a provider hands to the service container.
 */
import type ts from 'typescript';
import type { Declarations } from '../declarations';
import { returnedBy } from '../resolved-object';
import type { ResolvedObject } from '../resolved-object';
import { isFunctionNode } from '../script-nodes';

/**
 * @private
 *
 * What the provider of `addServiceProvider('<name>', <provider>)` returns.
 */
export function providedBy(provider: ts.Expression, source: ts.SourceFile, declarations: Declarations): ResolvedObject {
    const fn = declarations.valueOf(provider, source);

    return isFunctionNode(fn.node)
        ? returnedBy({ node: fn.node, source: fn.source }, declarations)
        : { kind: 'unresolved', expression: fn };
}
