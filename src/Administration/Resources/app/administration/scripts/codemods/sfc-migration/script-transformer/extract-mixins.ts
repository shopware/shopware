import type { Expression, ObjectLiteralExpression, SourceFile } from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import type { ComposableDescriptor } from './composable-registry';
import { collectConfigKeys, findMixinDescriptorByName, findMixinDescriptorByNameConstant } from './composable-registry';
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

    const ownMemberNames = collectComponentMemberNames(optionsObj, collectConfigKeys(descriptors));
    for (const descriptor of descriptors) {
        const trigger = descriptor.trigger;
        if (trigger.type !== 'mixin') {
            continue;
        }

        unresolved.push(...collectConfigKeyIssues(descriptor, optionsObj));

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
        // template read resolves to nothing. Back off if any is used — unless the
        // component declares its own member of that name, which shadows the mixin's
        // (Vue override semantics), so the missing composable binding is harmless.
        for (const member of trigger.unmappedMembers ?? []) {
            if (ownMemberNames.has(member)) {
                continue;
            }
            if (readsThisMember(optionsObj, member) || templateReferences.has(member)) {
                unresolved.push(
                    `mixins: reads '${member}' from the '${descriptor.id}' mixin, which the composable does not provide`,
                );
            }
        }
    }

    return { hasMixins: true, descriptors, unresolved };
}

/**
 * A config key's `data()` entry only sets the initial value of state the
 * composable owns. Declaring it anywhere else, or from an initializer that reads
 * the instance, cannot be expressed as a plain option, so the component backs off.
 */
function collectConfigKeyIssues(descriptor: ComposableDescriptor, optionsObj: ObjectLiteralExpression): string[] {
    const configKeys = descriptor.configKeys ?? [];
    if (configKeys.length === 0) {
        return [];
    }

    const dataProps = extractDataProps(optionsObj).dataProps;
    const dataPropsByName = new Map(dataProps.map((prop) => [prop.name, prop]));
    const nonDataMemberNames = collectComponentMemberNames(optionsObj, new Set(dataPropsByName.keys()));

    return configKeys.flatMap((key) => {
        if (nonDataMemberNames.has(key)) {
            return [
                `mixins: component declares '${key}' from the '${descriptor.id}' mixin outside of data(), which the composable cannot take as configuration`,
            ];
        }

        const dataProp = dataPropsByName.get(key);
        if (dataProp && readsInstance(dataProp.valueText)) {
            return [
                `mixins: the '${key}' data entry configuring the '${descriptor.id}' mixin reads the component instance`,
            ];
        }

        return [];
    });
}

/** Whether an expression reads `this`, and therefore cannot be emitted as a composable option. */
function readsInstance(valueText: string): boolean {
    return /\bthis\b/u.test(valueText);
}

/**
 * Names the component binds via its own `methods`, `computed`, `props`, `data`, or
 * `inject`. `excludedDataNames` drops `data()` entries that are not component state
 * — config keys, whose value is handed to a composable instead.
 */
export function collectComponentMemberNames(
    optionsObj: ObjectLiteralExpression,
    excludedDataNames: Set<string> = new Set(),
): Set<string> {
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
    extractDataProps(optionsObj)
        .dataProps.filter((prop) => !excludedDataNames.has(prop.name))
        .forEach((prop) => names.add(prop.name));
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
    // String form (`mixins: ['notification']`). Shopware's vue adapter resolves it
    // via `Mixin.getByName(mixin)`, so it is equivalent to the `getByName` call form.
    if (Node.isStringLiteral(element)) {
        return resolveMixinName(element.getLiteralValue());
    }

    if (Node.isCallExpression(element)) {
        const name = extractGetByNameArgument(element);
        if (name !== undefined) {
            return resolveMixinName(name);
        }

        // `getByName(MIXIN_NAME_CONSTANT)`: the value lives in another module, so
        // it can only be resolved through a descriptor that registers the export.
        const constantArgument = extractGetByNameIdentifierArgument(element);
        if (constantArgument) {
            const imported = resolveImportedIdentifier(constantArgument, sourceFile);
            const descriptor = imported
                ? findMixinDescriptorByNameConstant(imported.moduleSpecifier, imported.importedName)
                : undefined;

            if (descriptor) {
                return descriptor;
            }
        }

        // Factory form `getByName('base')(<arg>)`: the base mixin drives resolution
        // and `<arg>` is a factory parameter, not a mixin name. Report the base so
        // the reason is not mistaken for the argument (e.g. a bare 'salutation').
        const inner = element.getExpression();
        if (Node.isCallExpression(inner)) {
            const factoryName = extractGetByNameArgument(inner);
            if (factoryName !== undefined) {
                return `mixins: no composable registered for factory mixin '${factoryName}'`;
            }
        }

        return `mixins: unrecognised mixin call '${element.getText()}'`;
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

/** Resolves a mixin name to its descriptor, or an actionable backoff reason. */
function resolveMixinName(name: string): ComposableDescriptor | string {
    return findMixinDescriptorByName(name) ?? `mixins: no composable registered for mixin '${name}'`;
}

/** Extracts the string name from `*.getByName('name')`, or undefined. */
function extractGetByNameArgument(call: import('ts-morph').CallExpression): string | undefined {
    const argument = getByNameArgument(call);

    return argument && Node.isStringLiteral(argument) ? argument.getLiteralValue() : undefined;
}

/** Extracts the identifier from `*.getByName(NAME)`, or undefined. */
function extractGetByNameIdentifierArgument(
    call: import('ts-morph').CallExpression,
): import('ts-morph').Identifier | undefined {
    const argument = getByNameArgument(call);

    return argument && Node.isIdentifier(argument) ? argument : undefined;
}

function getByNameArgument(call: import('ts-morph').CallExpression): Expression | undefined {
    const expression = call.getExpression();
    if (!Node.isPropertyAccessExpression(expression) || expression.getName() !== 'getByName') {
        return undefined;
    }

    return call.getArguments()[0]?.asKind(SyntaxKind.StringLiteral) ?? call.getArguments()[0]?.asKind(SyntaxKind.Identifier);
}

/** Resolves the module specifier an imported identifier was imported from. */
function resolveImportedIdentifierModule(identifier: import('ts-morph').Identifier, sourceFile: SourceFile): string | undefined {
    return resolveImportedIdentifier(identifier, sourceFile)?.moduleSpecifier;
}

/** Resolves an identifier to the module it was imported from and its exported name. */
function resolveImportedIdentifier(
    identifier: import('ts-morph').Identifier,
    sourceFile: SourceFile,
): { moduleSpecifier: string; importedName: string } | undefined {
    const name = identifier.getText();

    for (const importDeclaration of sourceFile.getImportDeclarations()) {
        const moduleSpecifier = importDeclaration.getModuleSpecifierValue();

        if (importDeclaration.getDefaultImport()?.getText() === name) {
            return { moduleSpecifier, importedName: 'default' };
        }

        for (const namedImport of importDeclaration.getNamedImports()) {
            const localName = namedImport.getAliasNode()?.getText() ?? namedImport.getName();
            if (localName === name) {
                return { moduleSpecifier, importedName: namedImport.getName() };
            }
        }
    }

    return undefined;
}
