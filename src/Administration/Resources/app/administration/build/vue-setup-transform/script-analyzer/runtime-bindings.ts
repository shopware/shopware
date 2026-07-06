/**
 * @sw-package framework
 */

import type { ImportDeclaration, Node as BabelNode, Statement, VariableDeclarator } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { getNodeRange, unwrapTransparentMacroExpression } from './utils';
import type { ShopwareSetupMode } from '../utils/shopware-setup-block';

type RuntimeBinding = {
    name: string;
    node: BabelNode;
};

/**
 * TypeScript ambient declarations are compile-time only and must not become setup callback code or returned state.
 */
function isTypeScriptDeclareDeclaration(statement: Statement): boolean {
    const maybeDeclaredStatement = statement as Statement & { declare?: boolean };

    return Boolean(
        maybeDeclaredStatement.declare &&
            (statement.type === 'VariableDeclaration' ||
                statement.type === 'TSDeclareFunction' ||
                statement.type === 'ClassDeclaration' ||
                statement.type === 'TSEnumDeclaration' ||
                statement.type === 'TSModuleDeclaration'),
    );
}

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
    if (!pattern) {
        return;
    }

    if (pattern.type === 'Identifier') {
        addRuntimeBinding(runtimeBindings, runtimeBindingNames, pattern.name, pattern, scriptOffset);
        return;
    }

    if (pattern.type === 'RestElement') {
        collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, pattern.argument, scriptOffset);
        return;
    }

    if (pattern.type === 'AssignmentPattern') {
        collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, pattern.left, scriptOffset);
        return;
    }

    if (pattern.type === 'ArrayPattern') {
        pattern.elements.forEach((element) => {
            collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, element, scriptOffset);
        });
        return;
    }

    if (pattern.type === 'ObjectPattern') {
        pattern.properties.forEach((property) => {
            if (property.type === 'RestElement') {
                collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, property.argument, scriptOffset);
                return;
            }

            collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, property.value, scriptOffset);
        });
    }
}

/**
 * Allows setup input helper aliases without returning them as component state.
 */
function isRuntimeInputAlias(declaration: VariableDeclarator, mode: ShopwareSetupMode): boolean {
    const runtimeInputHelpers =
        mode === 'base'
            ? new Set([
                  'useSwContext',
              ])
            : new Set([
                  'useSwPreviousState',
                  'useSwProps',
                  'useSwContext',
              ]);

    return (
        declaration.id.type === 'Identifier' &&
        declaration.init?.type === 'CallExpression' &&
        declaration.init.callee.type === 'Identifier' &&
        runtimeInputHelpers.has(declaration.init.callee.name)
    );
}

/**
 * Rejects destructured props macros before lowering.
 *
 * The props macro is hoisted out of the setup callback, so destructuring `(__shopwareProps)` snapshots
 * the value once (dropping reactivity) and the collected names collide with the declared prop keys that
 * `createExtendableSetup()` deletes from returned state. Authors keep the props object and read
 * `props.<name>` instead.
 *
 * `defineProps` gets the expectation-correcting message: Vue 3.5's reactive props destructure lets you
 * declare defaults inline (`const { count = 1 } = defineProps()`), and that is the surprising case that
 * silently would not work here. Destructuring `withDefaults(...)` is already a non-idiomatic pattern, so
 * it only gets the plain "read props.<name>" guidance.
 */
function assertSupportedSetupInputDestructure(declaration: VariableDeclarator, scriptOffset: number): void {
    const init = unwrapTransparentMacroExpression(declaration.init);
    const calleeName =
        init?.type === 'CallExpression' && init.callee.type === 'Identifier' ? init.callee.name : null;
    const index = scriptOffset + getNodeRange(declaration.id, scriptOffset).start;

    if (calleeName === 'defineProps') {
        throw new ShopwareSetupTransformError(
            'Destructuring defineProps() is not supported in Shopware setup blocks: defaults declared through ' +
                'destructuring (const { count = 1 } = defineProps()) are not applied. Assign the macro to a ' +
                'variable such as `const props = defineProps(...)` and read `props.<name>`, and use ' +
                '`withDefaults(defineProps(...), { ... })` for defaults.',
            index,
        );
    }

    if (calleeName === 'withDefaults') {
        throw new ShopwareSetupTransformError(
            'Destructuring the props object is not supported in Shopware setup blocks. Assign ' +
                '`withDefaults(defineProps(...), { ... })` to a variable such as `const props = ...` and read ' +
                '`props.<name>`.',
            index,
        );
    }
}

/**
 * Checks whether a variable declaration reads setup input through a supported helper/macro.
 */
function isSetupInputDeclaration(declaration: VariableDeclarator): boolean {
    const init = unwrapTransparentMacroExpression(declaration.init);

    return (
        init?.type === 'CallExpression' &&
        init.callee.type === 'Identifier' &&
        (init.callee.name === 'defineProps' ||
            init.callee.name === 'defineEmits' ||
            init.callee.name === 'defineSlots' ||
            init.callee.name === 'withDefaults' ||
            init.callee.name === 'useSwProps')
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
        (init.callee.name === 'defineProps' ||
            init.callee.name === 'defineEmits' ||
            init.callee.name === 'defineSlots' ||
            init.callee.name === 'withDefaults')
    );
}

/**
 * Classifies top-level declarations that become private/base or override state.
 */
function collectRuntimeBinding(
    statement: Statement,
    runtimeBindings: RuntimeBinding[],
    runtimeBindingNames: Set<string>,
    scriptOffset: number,
    mode: ShopwareSetupMode,
): void {
    if (isTypeScriptDeclareDeclaration(statement)) {
        // Vue/TypeScript treat ambient declarations as type-only. The lowered callback only contains runtime setup
        // code, so keeping or returning these declarations would produce invalid output.
        throw new ShopwareSetupTransformError(
            'TypeScript declare declarations are not runtime Shopware setup bindings.',
            scriptOffset + getNodeRange(statement, scriptOffset).start,
        );
    }

    if (statement.type === 'VariableDeclaration') {
        statement.declarations.forEach((declaration) => {
            if (isSetupInputDeclaration(declaration)) {
                if (declaration.id.type !== 'Identifier') {
                    assertSupportedSetupInputDestructure(declaration, scriptOffset);
                    collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, declaration.id, scriptOffset);

                    return;
                }

                if (mode === 'base' && isExposableSetupMacroDeclaration(declaration) && declaration.id.type === 'Identifier') {
                    addRuntimeBinding(runtimeBindings, runtimeBindingNames, declaration.id.name, declaration.id, scriptOffset);
                }

                return;
            }

            if (isRuntimeInputAlias(declaration, mode)) {
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
