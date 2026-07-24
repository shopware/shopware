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
 * Whether a declaration initializes from a props macro (`defineProps` / `withDefaults`).
 *
 * A destructured props macro is left in place for Vue rather than collected as runtime state: a
 * destructured `defineProps()` gets Vue 3.5's reactive-props-destructure rewrite, and a destructured
 * `withDefaults()` gets Vue's own "reactive destructure disabled" warning. Both are Vue's concern.
 */
function isPropsMacroDeclaration(declaration: VariableDeclarator): boolean {
    const init = unwrapTransparentMacroExpression(declaration.init);
    const calleeName = init?.type === 'CallExpression' && init.callee.type === 'Identifier' ? init.callee.name : null;

    return calleeName === 'defineProps' || calleeName === 'withDefaults';
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
                    // A destructured props macro is left in place for Vue (reactive-props-destructure,
                    // or Vue's own withDefaults warning) - not renamed, not returned as state. Other
                    // setup-input macros (defineSlots/defineEmits) destructure into ordinary bindings.
                    if (!isPropsMacroDeclaration(declaration)) {
                        collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, declaration.id, scriptOffset);
                    }

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
