/**
 * @sw-package framework
 */

/**
 * Collects top-level values that become Shopware setup runtime state.
 *
 * This module separates imported names, setup input helper aliases, and user declarations so lowerers
 * return only state that should be visible to templates or override callbacks.
 */

import type { ImportDeclaration, Node as BabelNode, Statement, VariableDeclarator } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { getNodeRange, unwrapTransparentMacroExpression } from './utils';
import { forEachPatternIdentifier } from '../utils/babel-patterns';
import type { ShopwareSetupMode } from '../utils/shopware-setup-block';
import { getExposableSetupMacroNames, getRuntimeInputAliasNames, getSetupInputMacroNames } from './macro-registry';

const SETUP_INPUT_MACRO_NAMES = getSetupInputMacroNames();
const EXPOSABLE_SETUP_MACRO_NAMES = getExposableSetupMacroNames();
const RUNTIME_INPUT_ALIAS_NAMES: Record<ShopwareSetupMode, Set<string>> = {
    base: getRuntimeInputAliasNames('base'),
    override: getRuntimeInputAliasNames('override'),
};

/**
 * Represents one top-level runtime value that can be returned as setup state.
 */
type RuntimeBinding = {
    name: string;
    node: BabelNode;
};

/**
 * Tracks import locals so imports stay preserved but are never returned as state.
 */
function collectImportBindings(importNode: ImportDeclaration, importedBindings: Set<string>): void {
    importNode.specifiers.forEach((specifier) => {
        if (!specifier.local?.name) {
            return;
        }

        importedBindings.add(specifier.local.name);
    });
}

/**
 * Adds a top-level runtime binding and rejects duplicates before lowering.
 */
function addRuntimeBinding(
    runtimeBindings: RuntimeBinding[],
    runtimeBindingNames: Set<string>,
    name: string,
    node: BabelNode,
    scriptOffset: number,
): void {
    if (runtimeBindingNames.has(name)) {
        // Vue mostly relies on JavaScript parser scope errors here. Shopware also rejects duplicate collected names
        // explicitly because aliases such as var/function combinations can otherwise overwrite returned state.
        throw new ShopwareSetupTransformError(
            `Duplicate top-level Shopware setup binding "${name}".`,
            scriptOffset + getNodeRange(node, scriptOffset).start,
        );
    }

    runtimeBindingNames.add(name);
    runtimeBindings.push({
        name,
        node,
    });
}

/**
 * Collects runtime-visible setup bindings from one declaration pattern.
 */
function collectRuntimeBindingPattern(
    runtimeBindings: RuntimeBinding[],
    runtimeBindingNames: Set<string>,
    pattern: BabelNode | null | undefined,
    scriptOffset: number,
): void {
    forEachPatternIdentifier(pattern, (identifier) => {
        addRuntimeBinding(runtimeBindings, runtimeBindingNames, identifier.name, identifier, scriptOffset);
    });
}

/**
 * Allows setup input helper aliases without returning them as component state.
 *
 * e.g. `const context = useSwContext();` - the alias is usable locally but is not setup state.
 */
function isRuntimeInputAlias(declaration: VariableDeclarator, mode: ShopwareSetupMode): boolean {
    return (
        declaration.id.type === 'Identifier' &&
        declaration.init?.type === 'CallExpression' &&
        declaration.init.callee.type === 'Identifier' &&
        RUNTIME_INPUT_ALIAS_NAMES[mode].has(declaration.init.callee.name)
    );
}

/**
 * Rejects destructuring a `withDefaults(...)` result.
 *
 * A destructured `defineProps()` is supported: Vue 3.5 rewrites the destructured names into reactive
 * `props.<name>` reads (including inline defaults), and the base body is left in place for Vue to do
 * that. `withDefaults(...)` returns a plain object, so destructuring it snapshots the value once and
 * drops reactivity — reject it and point authors at inline destructure defaults or `props.<name>`.
 */
function assertSupportedSetupInputDestructure(declaration: VariableDeclarator, scriptOffset: number): void {
    const init = unwrapTransparentMacroExpression(declaration.init);
    const calleeName = init?.type === 'CallExpression' && init.callee.type === 'Identifier' ? init.callee.name : null;

    if (calleeName === 'withDefaults') {
        throw new ShopwareSetupTransformError(
            'Destructuring the props object is not supported in Shopware setup blocks. Destructure ' +
                'defineProps() directly (const { count = 1 } = defineProps<...>()) for reactive props with ' +
                'defaults, or assign the macro to a variable such as `const props = ...` and read `props.<name>`.',
            scriptOffset + getNodeRange(declaration.id, scriptOffset).start,
        );
    }
}

/**
 * Checks whether a variable declaration reads setup input through a supported helper/macro.
 *
 * e.g. `const props = defineProps<Props>();` or `const props = useSwProps();`
 */
function isSetupInputDeclaration(declaration: VariableDeclarator): boolean {
    const init = unwrapTransparentMacroExpression(declaration.init);

    return (
        init?.type === 'CallExpression' && init.callee.type === 'Identifier' && SETUP_INPUT_MACRO_NAMES.has(init.callee.name)
    );
}

/**
 * A base props/emits/slots macro assigned to a plain identifier (`const emit = defineEmits(...)`).
 *
 * These variables are exposed as private setup state so the template can reference them through the
 * generated destructure (`emit`, `slots`, `props.<name>`) instead of relying on a hoisted top-level
 * identifier. The macro call itself is still hoisted/replaced separately by the lowering step.
 */
function isExposableSetupMacroDeclaration(declaration: VariableDeclarator): boolean {
    const init = unwrapTransparentMacroExpression(declaration.init);

    return (
        declaration.id.type === 'Identifier' &&
        init?.type === 'CallExpression' &&
        init.callee.type === 'Identifier' &&
        EXPOSABLE_SETUP_MACRO_NAMES.has(init.callee.name)
    );
}

/**
 * Classifies top-level declarations that become private/base or override state.
 *
 * Runtime input aliases (`useSwPreviousState()`, `useSwProps()`, `useSwContext()`) are not returned as
 * independent setup state, but their names are recorded so template analysis can still forward them to
 * an override slot scope when the override template references them.
 */
function collectRuntimeBinding(
    statement: Statement,
    runtimeBindings: RuntimeBinding[],
    runtimeBindingNames: Set<string>,
    runtimeInputAliasNames: Set<string>,
    scriptOffset: number,
    mode: ShopwareSetupMode,
): void {
    if (statement.type === 'VariableDeclaration') {
        statement.declarations.forEach((declaration) => {
            if (isSetupInputDeclaration(declaration)) {
                if (declaration.id.type !== 'Identifier') {
                    // Destructured props. withDefaults destructure is rejected (snapshots, loses
                    // reactivity); a destructured defineProps() is left untouched - not renamed, not
                    // returned as state - so Vue 3.5's reactive-destructure rewrite handles it.
                    assertSupportedSetupInputDestructure(declaration, scriptOffset);

                    return;
                }

                if (mode === 'base' && isExposableSetupMacroDeclaration(declaration)) {
                    addRuntimeBinding(
                        runtimeBindings,
                        runtimeBindingNames,
                        declaration.id.name,
                        declaration.id,
                        scriptOffset,
                    );
                } else if (isRuntimeInputAlias(declaration, mode)) {
                    // e.g. override `const props = useSwProps()`: useSwProps is both a setup input and a
                    // runtime input alias, so it is not returned as state, but its name is recorded so an
                    // override template referencing it is forwarded to the generated <sw-block extends>
                    // slot scope like useSwPreviousState()/useSwContext().
                    runtimeInputAliasNames.add(declaration.id.name);
                }

                return;
            }

            if (isRuntimeInputAlias(declaration, mode)) {
                if (declaration.id.type === 'Identifier') {
                    runtimeInputAliasNames.add(declaration.id.name);
                }

                return;
            }

            collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, declaration.id, scriptOffset);
        });
        return;
    }

    if (statement.type === 'FunctionDeclaration' || statement.type === 'ClassDeclaration') {
        if (!statement.id?.name) {
            throw new ShopwareSetupTransformError(
                'Anonymous top-level declarations are not supported inside Shopware setup blocks.',
                scriptOffset + getNodeRange(statement, scriptOffset).start,
            );
        }

        addRuntimeBinding(runtimeBindings, runtimeBindingNames, statement.id.name, statement.id, scriptOffset);
        return;
    }

    if (statement.type === 'TSEnumDeclaration') {
        addRuntimeBinding(runtimeBindings, runtimeBindingNames, statement.id.name, statement.id, scriptOffset);
    }
}

export { type RuntimeBinding, collectImportBindings, collectRuntimeBinding };
