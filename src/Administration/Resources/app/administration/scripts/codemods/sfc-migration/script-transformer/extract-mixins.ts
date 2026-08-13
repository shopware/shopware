import type { Expression, ObjectLiteralExpression, SourceFile } from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import type { ComposableDescriptor } from './composable-registry';
import { findMixinDescriptorByName } from './composable-registry';
import { extractPropNamesFromText } from './extract-component-options';
import { extractDataProps } from './extract-data';
import { extractInjectProps } from './extract-inject';

export interface MixinResolution {
    /** Whether the component declares a `mixins` option at all. */
    hasMixins: boolean;
    /** Descriptors for every mixin that resolved to a registry entry. */
    descriptors: ComposableDescriptor[];
    /**
     * Human-readable reasons for mixins that could not be resolved. Conversion is
     * all-or-nothing: any unresolved entry keeps the component on the Options-API
     * backoff.
     */
    unresolved: string[];
}

/**
 * Parses `mixins: [...]` and maps each element to a composable descriptor. Handles
 * both `Shopware.Mixin.getByName('name')` (and the destructured `Mixin.getByName`
 * form) and imported mixin identifiers resolved by their module path. Anything the
 * parser cannot recognise is reported as unresolved rather than silently dropped.
 */
export function resolveComponentMixins(
    optionsObj: ObjectLiteralExpression,
    sourceFile: SourceFile,
    templateReferences: Set<string> = new Set(),
): MixinResolution {
    const mixinsProp = optionsObj.getProperty('mixins');
    if (!mixinsProp) {
        return { hasMixins: false, descriptors: [], unresolved: [] };
    }

    if (!Node.isPropertyAssignment(mixinsProp)) {
        return { hasMixins: true, descriptors: [], unresolved: ['mixins: unsupported declaration shape'] };
    }

    const initializer = mixinsProp.getInitializer();
    if (!initializer || !Node.isArrayLiteralExpression(initializer)) {
        return { hasMixins: true, descriptors: [], unresolved: ['mixins: expected an array literal'] };
    }

    const descriptors: ComposableDescriptor[] = [];
    const unresolved: string[] = [];

    for (const element of initializer.getElements()) {
        const resolved = resolveMixinElement(element, sourceFile);
        if (typeof resolved === 'string') {
            unresolved.push(resolved);
        } else if (!descriptors.includes(resolved)) {
            descriptors.push(resolved);
        }
    }

    const ownMemberNames = collectComponentMemberNames(optionsObj);
    for (const descriptor of descriptors) {
        const trigger = descriptor.trigger;
        if (trigger.type !== 'mixin') {
            continue;
        }

        // Overriding a leaf member is fine — the component's version wins and the
        // composable member is simply dropped. Only a member the composable calls
        // internally is unsafe: the composable keeps using its own copy, so the
        // override would silently stop taking effect. Back off for those.
        for (const member of trigger.internallyReferencedMembers ?? []) {
            if (ownMemberNames.has(member)) {
                unresolved.push(`mixins: component redefines '${member}' from the '${descriptor.id}' mixin`);
            }
        }

        // Members the mixin exposes but the composable does not provide have no
        // setup binding after migration: a script read drops the member, a
        // template read resolves to nothing. Back off if any is used.
        for (const member of trigger.unmappedMembers ?? []) {
            if (readsThisMember(optionsObj, member) || templateReferences.has(member)) {
                unresolved.push(
                    `mixins: reads '${member}' from the '${descriptor.id}' mixin, which the composable does not provide`,
                );
            }
        }
    }

    return { hasMixins: true, descriptors, unresolved };
}

/** Names the component binds via its own `methods`, `computed`, `props`, `data`, or `inject`. */
export function collectComponentMemberNames(optionsObj: ObjectLiteralExpression): Set<string> {
    const names = new Set<string>();

    for (const option of [
        'methods',
        'computed',
    ]) {
        const prop = optionsObj.getProperty(option);
        const initializer = prop && Node.isPropertyAssignment(prop) ? prop.getInitializer() : undefined;
        if (!initializer || !Node.isObjectLiteralExpression(initializer)) {
            continue;
        }

        for (const member of initializer.getProperties()) {
            if (
                Node.isPropertyAssignment(member) ||
                Node.isMethodDeclaration(member) ||
                Node.isGetAccessorDeclaration(member) ||
                Node.isSetAccessorDeclaration(member) ||
                Node.isShorthandPropertyAssignment(member)
            ) {
                names.add(member.getName());
            }
        }
    }

    // props/data/inject bind into the same instance namespace, so a mixin member
    // one of them shadows is overridden just like a method would be. Mirror the
    // override set used when the composable members are emitted.
    extractPropNamesFromText(optionsObj).forEach((name) => names.add(name));
    extractDataProps(optionsObj).dataProps.forEach((prop) => names.add(prop.name));
    extractInjectProps(optionsObj).injectProps.forEach((prop) => names.add(prop.localName));

    return names;
}

/** Whether the component reads `this.<name>` anywhere in its options object. */
function readsThisMember(optionsObj: ObjectLiteralExpression, name: string): boolean {
    return optionsObj
        .getDescendantsOfKind(SyntaxKind.PropertyAccessExpression)
        .some((access) => access.getExpression().isKind(SyntaxKind.ThisKeyword) && access.getName() === name);
}

/** Returns the descriptor for a single `mixins` array element, or a reason string. */
function resolveMixinElement(element: Expression, sourceFile: SourceFile): ComposableDescriptor | string {
    if (Node.isCallExpression(element)) {
        const name = extractGetByNameArgument(element);
        if (name === undefined) {
            return `mixins: unrecognised mixin call '${element.getText()}'`;
        }

        return findMixinDescriptorByName(name) ?? `mixins: no composable registered for mixin '${name}'`;
    }

    if (Node.isIdentifier(element)) {
        // Imported mixin identifiers are never converted (no registry entry maps a
        // module path); report the module so the backoff reason is actionable.
        const moduleSpecifier = resolveImportedIdentifierModule(element, sourceFile);
        if (moduleSpecifier === undefined) {
            return `mixins: could not resolve imported mixin '${element.getText()}'`;
        }

        return `mixins: no composable registered for mixin module '${moduleSpecifier}'`;
    }

    return `mixins: unsupported mixin element '${element.getText()}'`;
}

/** Extracts the string name from `*.getByName('name')`, or undefined. */
function extractGetByNameArgument(call: import('ts-morph').CallExpression): string | undefined {
    const expression = call.getExpression();
    if (!Node.isPropertyAccessExpression(expression) || expression.getName() !== 'getByName') {
        return undefined;
    }

    const argument = call.getArguments()[0];
    if (argument && Node.isStringLiteral(argument)) {
        return argument.getLiteralValue();
    }

    return undefined;
}

/** Resolves the module specifier an imported identifier was imported from. */
function resolveImportedIdentifierModule(identifier: import('ts-morph').Identifier, sourceFile: SourceFile): string | undefined {
    const name = identifier.getText();

    for (const importDeclaration of sourceFile.getImportDeclarations()) {
        if (importDeclaration.getDefaultImport()?.getText() === name) {
            return importDeclaration.getModuleSpecifierValue();
        }

        for (const namedImport of importDeclaration.getNamedImports()) {
            const localName = namedImport.getAliasNode()?.getText() ?? namedImport.getName();
            if (localName === name) {
                return importDeclaration.getModuleSpecifierValue();
            }
        }
    }

    return undefined;
}
