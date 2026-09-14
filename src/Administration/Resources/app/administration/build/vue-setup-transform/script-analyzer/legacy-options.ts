/**
 * @sw-package framework
 */

import type { File, Node } from '@babel/types';
import { COMPOSABLE_DESCRIPTORS } from '../../../scripts/codemods/sfc-migration/composables/descriptors';

type LegacyMemberKind = 'data' | 'computed' | 'writable-computed' | 'method';
type LegacyOptionsMetadata = {
    members: Record<string, LegacyMemberKind>;
    bindings: Record<string, string>;
};
type Declaration = { value: Node | null; constant: boolean; property?: string };
type ImportedFunction = { source: string; name: string };

/** Type annotations do not change the value or its original declaration. */
function unwrap(node: Node | null): Node | null {
    if (
        node?.type === 'TSAsExpression' ||
        node?.type === 'TSSatisfiesExpression' ||
        node?.type === 'TSNonNullExpression' ||
        node?.type === 'TSTypeAssertion' ||
        node?.type === 'ParenthesizedExpression'
    ) {
        return unwrap(node.expression);
    }

    return node;
}

function propertyName(node: Node, computed = false): string | undefined {
    if (node.type === 'StringLiteral') return node.value;
    if (node.type === 'Identifier' && !computed) return node.name;
    return undefined;
}

/**
 * Infers the native Options surface from source, before setup renaming erases declaration shapes.
 * Unknown factory results stay unclassified: their runtime value cannot reveal whether an old
 * Options member was data or computed. Only the codemod's audited mappings supply that contract.
 */
function inferLegacyOptions(ast: File, publicNames: string[]): LegacyOptionsMetadata {
    const declarations = new Map<string, Declaration>();
    const imports = new Map<string, ImportedFunction>();
    const namespaces = new Map<string, string>();

    ast.program.body.forEach((statement) => {
        if (statement.type === 'ImportDeclaration' && statement.importKind !== 'type') {
            statement.specifiers.forEach((specifier) => {
                if (specifier.type === 'ImportNamespaceSpecifier') {
                    namespaces.set(specifier.local.name, statement.source.value);
                } else if (specifier.type === 'ImportDefaultSpecifier') {
                    imports.set(specifier.local.name, { source: statement.source.value, name: 'default' });
                } else if (specifier.type === 'ImportSpecifier' && specifier.importKind !== 'type') {
                    imports.set(specifier.local.name, {
                        source: statement.source.value,
                        name: propertyName(specifier.imported)!,
                    });
                }
            });
        }
        if (statement.type === 'FunctionDeclaration' && statement.id) {
            declarations.set(statement.id.name, { value: statement, constant: true });
        }
        if (statement.type !== 'VariableDeclaration') return;

        statement.declarations.forEach(({ id, init }) => {
            const declaration = { value: init ?? null, constant: statement.kind === 'const' };
            if (id.type === 'Identifier') declarations.set(id.name, declaration);
            if (id.type !== 'ObjectPattern') return;

            id.properties.forEach((property) => {
                if (property.type !== 'ObjectProperty' || property.value.type !== 'Identifier') return;
                const name = propertyName(property.key, property.computed);
                if (name) declarations.set(property.value.name, { ...declaration, property: name });
            });
        });
    });

    function importedFunction(node: Node | null, seen = new Set<string>()): ImportedFunction | undefined {
        const value = unwrap(node);
        if (value?.type === 'Identifier') {
            if (seen.has(value.name)) return undefined;
            seen.add(value.name);
            const imported = imports.get(value.name);
            const declaration = declarations.get(value.name);
            return imported ?? (declaration?.constant ? importedFunction(declaration.value, seen) : undefined);
        }
        if (value?.type === 'MemberExpression' && value.object.type === 'Identifier') {
            const source = namespaces.get(value.object.name);
            const name = propertyName(value.property, value.computed);
            if (source && name) return { source, name };
        }
        return undefined;
    }

    function resolveValue(node: Node | null, seen = new Set<string>()): Node | null {
        const value = unwrap(node);
        if (value?.type !== 'Identifier' || seen.has(value.name)) return value;
        const declaration = declarations.get(value.name);
        if (!declaration?.constant || declaration.property) return value;
        seen.add(value.name);
        return resolveValue(declaration.value, seen);
    }

    function composableMember(node: Node | null, name: string): LegacyMemberKind | undefined {
        const value = resolveValue(node);
        if (value?.type !== 'CallExpression') return undefined;
        const imported = importedFunction(value.callee);
        const descriptor = COMPOSABLE_DESCRIPTORS.find(
            (entry) =>
                // Descriptor names are the codemod's local names; these composables export a default function.
                entry.legacyCompatible && entry.import.source === imported?.source && imported.name === 'default',
        );
        const member = Object.entries(descriptor?.members ?? {}).find(
            ([
                key,
                entry,
            ]) => (entry.sourceKey ?? key) === name,
        )?.[1];
        // A descriptor's `ref` can represent either data or computed, so it is not evidence of either.
        return member?.kind === 'method' ? 'method' : undefined;
    }

    function classify(node: Node | null, seen = new Set<string>()): LegacyMemberKind | undefined {
        const value = unwrap(node);
        if (!value) return 'data';
        if (value.type === 'Identifier') {
            if (seen.has(value.name)) return undefined;
            seen.add(value.name);
            const declaration = declarations.get(value.name);
            if (!declaration) return undefined;
            if (declaration.property)
                return declaration.constant ? composableMember(declaration.value, declaration.property) : undefined;
            const kind = classify(declaration.value, seen);
            // A mutable function binding can later hold data. Do not permanently install it as a method.
            return declaration.constant || kind === 'data' ? kind : undefined;
        }
        if (
            value.type === 'FunctionDeclaration' ||
            value.type === 'FunctionExpression' ||
            value.type === 'ArrowFunctionExpression'
        ) {
            return 'method';
        }
        if (value.type === 'MemberExpression') {
            const name = propertyName(value.property, value.computed);
            return name ? composableMember(value.object, name) : undefined;
        }
        if (value.type === 'CallExpression') {
            const imported = importedFunction(value.callee);
            if (imported?.source !== 'vue') return undefined;
            if (
                [
                    'ref',
                    'shallowRef',
                    'reactive',
                    'shallowReactive',
                    'customRef',
                ].includes(imported.name)
            )
                return 'data';
            if (imported.name !== 'computed') return undefined;
            const getter = resolveValue(value.arguments[0] ?? null);
            if (
                getter?.type === 'ArrowFunctionExpression' ||
                getter?.type === 'FunctionExpression' ||
                getter?.type === 'FunctionDeclaration'
            )
                return 'computed';
            if (getter?.type !== 'ObjectExpression') return undefined;
            if (getter.properties.some((property) => property.type === 'SpreadElement' || property.computed))
                return undefined;
            const properties = getter.properties.filter((property) => property.type !== 'SpreadElement');
            if (!properties.some((property) => propertyName(property.key) === 'get')) return undefined;
            const setter = properties.find((property) => propertyName(property.key) === 'set');
            if (!setter) return 'computed';
            if (setter.type === 'ObjectMethod') return 'writable-computed';
            const setterValue = resolveValue(setter.value);
            if (
                setterValue?.type === 'ArrowFunctionExpression' ||
                setterValue?.type === 'FunctionExpression' ||
                setterValue?.type === 'FunctionDeclaration'
            )
                return 'writable-computed';
            return undefined;
        }
        if (
            value.type === 'ObjectExpression' ||
            value.type === 'ArrayExpression' ||
            value.type === 'StringLiteral' ||
            value.type === 'NumericLiteral' ||
            value.type === 'BooleanLiteral' ||
            value.type === 'NullLiteral' ||
            value.type === 'BigIntLiteral' ||
            value.type === 'RegExpLiteral' ||
            value.type === 'TemplateLiteral'
        )
            return 'data';
        return undefined;
    }

    function originalName(name: string, seen = new Set<string>()): string {
        if (seen.has(name)) return name;
        seen.add(name);
        const declaration = declarations.get(name);
        const value = unwrap(declaration?.value ?? null);
        if (
            declaration?.constant &&
            !declaration.property &&
            value?.type === 'Identifier' &&
            declarations.get(value.name)?.constant
        ) {
            return originalName(value.name, seen);
        }
        return name;
    }

    const members: LegacyOptionsMetadata['members'] = Object.create(null) as LegacyOptionsMetadata['members'];
    const bindings: LegacyOptionsMetadata['bindings'] = Object.create(null) as LegacyOptionsMetadata['bindings'];
    publicNames.forEach((name) => {
        const kind = classify({ type: 'Identifier', name });
        if (kind) members[name] = kind;
        const original = originalName(name);
        const originalValue = resolveValue(declarations.get(original)?.value ?? null);
        // Copying a primitive into a const does not create a live alias. Only shared objects, refs
        // and functions should route internal reads through the exposed name.
        const sharedValue =
            kind &&
            (kind !== 'data' ||
                originalValue?.type === 'CallExpression' ||
                originalValue?.type === 'ObjectExpression' ||
                originalValue?.type === 'ArrayExpression');
        if (original !== name && sharedValue) bindings[name] = original;
    });
    return { members, bindings };
}

/** @private */
export { inferLegacyOptions, type LegacyOptionsMetadata };
