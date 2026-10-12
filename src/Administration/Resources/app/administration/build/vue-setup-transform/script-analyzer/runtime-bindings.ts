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
import { absoluteRange, unwrapTransparentMacroExpression } from './utils';
import { forEachPatternIdentifier } from '../utils/babel-patterns';
import type { ShopwareSetupMode } from '../utils/shopware-setup-block';
import { EXPOSABLE_SETUP_MACRO_NAMES, SETUP_INPUT_MACRO_NAMES, getRuntimeInputAliasNames } from './macro-registry';

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
 * One imported local, with the import it came from and that import's source.
 *
 * Recorded while walking the specifiers so the reserved-name check gets `{ name, node, importSource }`
 * directly, instead of re-deriving it later with a nested search over every import.
 */
type ImportedBinding = {
    name: string;
    node: ImportDeclaration;
    importSource: string;
};

/**
 * Records every import local so imports stay preserved but are never returned as state.
 */
function collectImportBindings(importNode: ImportDeclaration, into: ImportedBinding[]): void {
    importNode.specifiers.forEach((specifier) => {
        if (!specifier.local?.name) {
            return;
        }

        into.push({
            name: specifier.local.name,
            node: importNode,
            importSource: String(importNode.source.value),
        });
    });
}

/**
 * Accumulates the top-level runtime bindings of one setup script.
 *
 * Owns the three results the classification pass produces together - the ordered bindings, the name
 * set used for duplicate detection and rename targeting, and the runtime input alias names - plus the
 * script offset every diagnostic needs. Keeping them here means they cannot drift apart and no caller
 * has to thread three accumulators through the recursion by hand.
 */
class RuntimeBindingCollector {
    readonly bindings: RuntimeBinding[] = [];

    /** Always exactly the names in `bindings`; kept as a Set for duplicate and rename lookups. */
    readonly names = new Set<string>();

    /** Names that alias a runtime input (`useSwProps()`), usable locally but never returned as state. */
    readonly aliasNames = new Set<string>();

    #scriptOffset: number;

    constructor(scriptOffset: number) {
        this.#scriptOffset = scriptOffset;
    }

    /**
     * Adds a top-level runtime binding and rejects duplicates before lowering.
     */
    add(name: string, node: BabelNode): void {
        if (this.names.has(name)) {
            // Vue mostly relies on JavaScript parser scope errors here. Shopware also rejects duplicate collected names
            // explicitly because aliases such as var/function combinations can otherwise overwrite returned state.
            throw new ShopwareSetupTransformError(
                `Duplicate top-level Shopware setup binding "${name}".`,
                absoluteRange(node, this.#scriptOffset),
            );
        }

        this.names.add(name);
        this.bindings.push({
            name,
            node,
        });
    }

    /**
     * Adds every runtime-visible binding one declaration pattern declares.
     */
    addPattern(pattern: BabelNode | null | undefined): void {
        forEachPatternIdentifier(pattern, (identifier) => {
            this.add(identifier.name, identifier);
        });
    }
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
 * generated footer destructure (`emit`, `slots`, `props.<name>`). The macro call itself is left exactly
 * where the author wrote it, for Vue to compile.
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
    collector: RuntimeBindingCollector,
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
                        collector.addPattern(declaration.id);
                    }

                    return;
                }

                if (mode === 'base' && isExposableSetupMacroDeclaration(declaration)) {
                    collector.add(declaration.id.name, declaration.id);
                } else if (isRuntimeInputAlias(declaration, mode)) {
                    // e.g. override `const props = useSwProps()`: useSwProps is both a setup input and a
                    // runtime input alias, so it is not returned as state, but its name is recorded so an
                    // override template referencing it is forwarded to the generated <sw-block extends>
                    // slot scope like useSwPreviousState()/useSwContext().
                    collector.aliasNames.add(declaration.id.name);
                }

                return;
            }

            if (isRuntimeInputAlias(declaration, mode)) {
                if (declaration.id.type === 'Identifier') {
                    collector.aliasNames.add(declaration.id.name);
                }

                return;
            }

            collector.addPattern(declaration.id);
        });
        return;
    }

    if (statement.type === 'FunctionDeclaration' || statement.type === 'ClassDeclaration') {
        if (!statement.id?.name) {
            throw new ShopwareSetupTransformError(
                'Anonymous top-level declarations are not supported inside Shopware setup blocks.',
                absoluteRange(statement, scriptOffset),
            );
        }

        collector.add(statement.id.name, statement.id);
        return;
    }

    if (statement.type === 'TSEnumDeclaration') {
        collector.add(statement.id.name, statement.id);
    }
}

/**
 * @private
 */
export { type ImportedBinding, type RuntimeBinding, RuntimeBindingCollector, collectImportBindings, collectRuntimeBinding };
