/**
 * @sw-package framework
 *
 * Finds the stores core registers: the calls of `Shopware.Store.register` with the definition each one gets.
 */
import ts from 'typescript';
import type { FlagContext } from '../../model/flag-states';
import type { SourceTree } from '../../source/source-tree';
import { findRegistrations } from '../registered-units';
import type { Registered } from '../registered-units';
import type { Declarations, Located } from '../declarations';
import { isMethodCall, memberName } from '../script-nodes';
import type { MethodCall } from '../script-nodes';

/**
 * @private
 *
 * One registered store. `definition` is what the call gets as the store: the object literal with its options,
 * the function that sets it up, or the expression the reader could not follow to one of them. `holder` is the
 * constant the store is assigned to, `const exampleStore = Shopware.Store.register(...)`, or the call itself.
 */
export interface StoreRegistration extends Registered {
    definition: Located;
    holder: Located;
}

const STORE_ACCESSOR = /(?:^|\.)Store(?:\.instance)?$/;

function isRegisterCall(node: ts.Node): node is MethodCall {
    return (
        isMethodCall(node) &&
        node.expression.name.text === 'register' &&
        STORE_ACCESSOR.test(node.expression.expression.getText())
    );
}

/** The `id` of `register({ id: 'example', ... })`. */
function idOption(definition: ts.Node): string | undefined {
    const option = ts.isObjectLiteralExpression(definition)
        ? definition.properties.find((property) => memberName(property) === 'id')
        : undefined;

    return option !== undefined && ts.isPropertyAssignment(option) && ts.isStringLiteralLike(option.initializer)
        ? option.initializer.text
        : undefined;
}

/** `register('example', <definition>)` names the store first, `register({ id: 'example', ... })` inside. */
function namedDefinition(
    call: MethodCall,
    source: ts.SourceFile,
    declarations: Declarations,
): { name: string; definition: Located } | undefined {
    const [first, second] = call.arguments;

    if (first === undefined) {
        return undefined;
    }

    if (ts.isStringLiteralLike(first)) {
        return second && { name: first.text, definition: declarations.valueOf(second, source) };
    }

    const definition = declarations.valueOf(first, source);
    const name = idOption(definition.node);

    return name === undefined ? undefined : { name, definition };
}

/**
 * @private
 *
 * Every store registration of the tree.
 */
export function storeRegistrations(tree: SourceTree, declarations: Declarations, flags: FlagContext): StoreRegistration[] {
    return findRegistrations(
        {
            mention: /\bStore\s*(?:\.\s*instance\s*)?\.\s*register\s*\(/,
            isCall: isRegisterCall,
            read: (call, source) => {
                const named = namedDefinition(call, source, declarations);

                return (
                    named && {
                        ...named,
                        holder: { node: ts.isVariableDeclaration(call.parent) ? call.parent : call, source },
                    }
                );
            },
            // `register` itself, which passes on the id it gets at runtime.
            forwardingFiles: ['src/app/store/index.ts'],
            needs: 'a store needs a literal id and a definition',
        },
        tree,
        declarations,
        flags,
    );
}
