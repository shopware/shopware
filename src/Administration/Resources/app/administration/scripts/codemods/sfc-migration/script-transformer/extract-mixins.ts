import type { Expression, ObjectLiteralExpression, SourceFile } from 'ts-morph';
import { Node } from 'ts-morph';
import type { ComposableDescriptor } from './composable-registry';
import { findMixinDescriptorByModule, findMixinDescriptorByName } from './composable-registry';

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
export function resolveComponentMixins(optionsObj: ObjectLiteralExpression, sourceFile: SourceFile): MixinResolution {
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

    // A component member that shadows a mixin member wins under Vue's override
    // semantics, but the composable calls its own copy internally, so the
    // override would silently stop taking effect. Keep the Options-API backoff.
    const ownMemberNames = collectComponentMemberNames(optionsObj);
    for (const descriptor of descriptors) {
        for (const member of Object.keys(descriptor.members)) {
            if (ownMemberNames.has(member)) {
                unresolved.push(`mixins: component redefines '${member}' from the '${descriptor.id}' mixin`);
            }
        }
    }

    return { hasMixins: true, descriptors, unresolved };
}

/** Names the component declares in its own `methods` / `computed` options. */
function collectComponentMemberNames(optionsObj: ObjectLiteralExpression): Set<string> {
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

    return names;
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
        const moduleSpecifier = resolveImportedIdentifierModule(element, sourceFile);
        if (moduleSpecifier === undefined) {
            return `mixins: could not resolve imported mixin '${element.getText()}'`;
        }

        return (
            findMixinDescriptorByModule(moduleSpecifier) ??
            `mixins: no composable registered for mixin module '${moduleSpecifier}'`
        );
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
