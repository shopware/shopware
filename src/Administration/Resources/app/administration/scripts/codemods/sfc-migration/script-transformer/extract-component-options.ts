import type { BindingName, Node as TsNode, ObjectLiteralElementLike, ObjectLiteralExpression } from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import { collectModuleLocalNames } from './ast';
import { hasUnsupportedWatchParameterShape } from './extract-watch';
import { getPropertyName } from './helpers';
import type { ComponentRegistration, EmitsDefinition } from './types';

export interface OptionShapeIssue {
    reason: string;
    /** true → the whole component must back off to the Options API. */
    backoff: boolean;
}

type RootPropertyClassification = { kind: 'spread' } | { kind: 'dynamic' } | { kind: 'named'; name: string };

/** Classifies a root component option by whether its key is static. */
function classifyRootProperty(prop: ObjectLiteralElementLike): RootPropertyClassification {
    if (prop.isKind(SyntaxKind.SpreadAssignment)) {
        return { kind: 'spread' };
    }

    if (prop.isKind(SyntaxKind.ShorthandPropertyAssignment)) {
        return { kind: 'named', name: prop.asKindOrThrow(SyntaxKind.ShorthandPropertyAssignment).getName() };
    }

    const nameNode =
        prop.isKind(SyntaxKind.PropertyAssignment) ||
        prop.isKind(SyntaxKind.MethodDeclaration) ||
        prop.isKind(SyntaxKind.GetAccessor) ||
        prop.isKind(SyntaxKind.SetAccessor)
            ? prop.getNameNode()
            : undefined;

    if (!nameNode) {
        return { kind: 'dynamic' };
    }

    if (Node.isComputedPropertyName(nameNode)) {
        const expression = nameNode.getExpression();

        // A computed key built from a literal (`['render']`) still resolves to a
        // static name; anything else depends on runtime state.
        if (expression.isKind(SyntaxKind.StringLiteral) || expression.isKind(SyntaxKind.NumericLiteral)) {
            return { kind: 'named', name: expression.getLiteralText() };
        }

        return { kind: 'dynamic' };
    }

    if (Node.isStringLiteral(nameNode) || Node.isNumericLiteral(nameNode)) {
        return { kind: 'named', name: nameNode.getLiteralText() };
    }

    return { kind: 'named', name: nameNode.getText() };
}

function referencesModuleLocal(node: TsNode, moduleLocalNames: Set<string>): boolean {
    if (moduleLocalNames.size === 0) {
        return false;
    }

    return node
        .getDescendantsOfKind(SyntaxKind.Identifier)
        .some((identifier) => moduleLocalNames.has(identifier.getText()) && isValueReference(identifier, node));
}

/**
 * A property key (`{ label: … }`) or a member name (`Shopware.Utils`) that
 * happens to match a module-local name is not a reference to it. Only value
 * positions count, so those static names must be excluded to avoid false
 * Options API backoffs. Shorthand entries (`{ label }`) are references.
 */
function isValueReference(identifier: TsNode, rootNode: TsNode): boolean {
    if (isDeclarationIdentifier(identifier) || isShadowedByLocalBinding(identifier, rootNode)) {
        return false;
    }

    const parent = identifier.getParent();
    if (!parent) {
        return true;
    }

    if (
        Node.isPropertyAssignment(parent) ||
        Node.isMethodDeclaration(parent) ||
        Node.isGetAccessorDeclaration(parent) ||
        Node.isSetAccessorDeclaration(parent) ||
        Node.isPropertyAccessExpression(parent)
    ) {
        return parent.getNameNode().getStart() !== identifier.getStart();
    }

    return true;
}

function isDeclarationIdentifier(identifier: TsNode): boolean {
    const parent = identifier.getParent();
    if (!parent) {
        return false;
    }

    if (Node.isBindingElement(parent) || Node.isParameterDeclaration(parent) || Node.isVariableDeclaration(parent)) {
        return parent.getNameNode().getStart() === identifier.getStart();
    }

    if (Node.isFunctionDeclaration(parent) || Node.isClassDeclaration(parent)) {
        return parent.getNameNode()?.getStart() === identifier.getStart();
    }

    return false;
}

function isShadowedByLocalBinding(identifier: TsNode, rootNode: TsNode): boolean {
    const name = identifier.getText();
    let current = identifier.getParent();

    while (current && current !== rootNode) {
        if (
            Node.isMethodDeclaration(current) ||
            Node.isFunctionDeclaration(current) ||
            Node.isFunctionExpression(current) ||
            Node.isArrowFunction(current)
        ) {
            if (current.getParameters().some((param) => bindingNameContains(param.getNameNode(), name))) {
                return true;
            }
        }

        current = current.getParent();
    }

    return false;
}

function bindingNameContains(nameNode: BindingName, name: string): boolean {
    if (Node.isIdentifier(nameNode)) {
        return nameNode.getText() === name;
    }

    return nameNode
        .getElements()
        .some((element) => Node.isBindingElement(element) && bindingNameContains(element.getNameNode(), name));
}

function analyzeObjectOrArrayOption(
    optionsObj: ObjectLiteralExpression,
    optionName: 'props' | 'emits',
    moduleLocalNames: Set<string>,
): OptionShapeIssue | null {
    const prop = optionsObj.getProperty(optionName);
    if (!prop) {
        return null;
    }

    if (!prop.isKind(SyntaxKind.PropertyAssignment)) {
        return { reason: `${optionName}: must be defined as an array or object literal`, backoff: false };
    }

    const initializer = prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();

    const arrayInit = initializer?.asKind(SyntaxKind.ArrayLiteralExpression);
    if (arrayInit) {
        const allStrings = arrayInit.getElements().every((element) => element.isKind(SyntaxKind.StringLiteral));

        return allStrings ? null : { reason: `${optionName}: array entries must be string literals`, backoff: false };
    }

    const objectInit = initializer?.asKind(SyntaxKind.ObjectLiteralExpression);
    if (objectInit) {
        if (referencesModuleLocal(objectInit, moduleLocalNames)) {
            return { reason: `${optionName}: definition references a module-local declaration`, backoff: true };
        }

        const hasNonStaticEntry = objectInit.getProperties().some((entry) => classifyRootProperty(entry).kind !== 'named');

        return hasNonStaticEntry
            ? { reason: `${optionName}: entries must use static keys without spreads`, backoff: false }
            : null;
    }

    return { reason: `${optionName}: initializer must be an array or object literal`, backoff: false };
}

export function analyzePropsShape(
    optionsObj: ObjectLiteralExpression,
    moduleLocalNames: Set<string>,
): OptionShapeIssue | null {
    return analyzeObjectOrArrayOption(optionsObj, 'props', moduleLocalNames);
}

export function analyzeEmitsShape(
    optionsObj: ObjectLiteralExpression,
    moduleLocalNames: Set<string>,
): OptionShapeIssue | null {
    return analyzeObjectOrArrayOption(optionsObj, 'emits', moduleLocalNames);
}

export function extractPropsText(optionsObj: ObjectLiteralExpression): string | null {
    const prop = optionsObj.getProperty('props');
    // Unsupported props shapes (shorthand, spreads, computed keys, non-literal)
    // are surfaced by analyzePropsShape, which suppresses this text to an empty
    // defineProps so unresolved `this.<prop>` accesses become manual follow-ups.
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return null;

    const initializer = prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();
    return initializer?.getText() ?? null;
}

export function extractEmitsDefinition(optionsObj: ObjectLiteralExpression): EmitsDefinition {
    const prop = optionsObj.getProperty('emits');
    // Unsupported emits shapes are surfaced by analyzeEmitsShape, which suppresses
    // this definition to inferred `$emit()` names when it cannot be emitted safely.
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return { keys: [], objectText: null };

    const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);

    // Example: `{ emits: ['save', 'cancel'] }`
    const arrayInit = pa.getInitializerIfKind(SyntaxKind.ArrayLiteralExpression);
    if (arrayInit) {
        return {
            keys: arrayInit
                .getElements()
                .filter((el) => el.isKind(SyntaxKind.StringLiteral))
                .map((el) => el.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue()),
            objectText: null,
        };
    }

    // Example: `{ emits: { save: null, cancel(payload) { return Boolean(payload); } } }`
    const objInit = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
    if (objInit) {
        return {
            keys: objInit
                .getProperties()
                // Examples: `{ emits: { save: null } }` and `{ emits: { save(payload) { return true; } } }`
                .filter((p) => p.isKind(SyntaxKind.PropertyAssignment) || p.isKind(SyntaxKind.MethodDeclaration))
                .map((p) =>
                    p.isKind(SyntaxKind.MethodDeclaration)
                        ? p.asKindOrThrow(SyntaxKind.MethodDeclaration).getName()
                        : p.asKindOrThrow(SyntaxKind.PropertyAssignment).getName(),
                ),
            objectText: objInit.getText(),
        };
    }

    return { keys: [], objectText: null };
}

export function extractInheritAttrs(optionsObj: ObjectLiteralExpression): boolean {
    const prop = optionsObj.getProperty('inheritAttrs');
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return true;

    const initializer = prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();
    // Dynamic inheritAttrs expressions collapse to `true` here; hasDynamicInheritAttrs
    // reports them so the migration is not silently marked complete.
    return initializer?.getText() !== 'false';
}

export function detectBlockers(optionsObj: ObjectLiteralExpression, registration: ComponentRegistration): string[] {
    const blockers: string[] = [];

    if (registration.isExtend) {
        blockers.push(
            registration.parentComponentName ? `extends (parent: ${registration.parentComponentName})` : 'extends',
        );
    }
    if (optionsObj.getProperty('mixins')) blockers.push('mixins');
    if (!registration.componentNameIsLiteral) {
        blockers.push('component name: non-literal component name requires manual migration');
    }

    // Root option spreads and dynamic option keys can hide arbitrary options
    // (including render), and a `render` key owns the component output; all
    // three make a partial conversion unsafe, so back off to the Options API.
    for (const prop of optionsObj.getProperties()) {
        const classification = classifyRootProperty(prop);

        if (classification.kind === 'spread') {
            blockers.push('component option spread requires manual migration');
        } else if (classification.kind === 'dynamic') {
            blockers.push('dynamic option key requires manual migration');
        } else if (classification.name === 'render') {
            blockers.push('render function');
        }
    }

    const moduleLocalNames = collectModuleLocalNames(registration.call.getSourceFile(), registration);
    const propsIssue = analyzePropsShape(optionsObj, moduleLocalNames);
    if (propsIssue?.backoff) blockers.push(propsIssue.reason);
    const emitsIssue = analyzeEmitsShape(optionsObj, moduleLocalNames);
    if (emitsIssue?.backoff) blockers.push(emitsIssue.reason);

    // A watcher signature that cannot be re-emitted equivalently would corrupt
    // the handler parameters, so keep the whole component on the Options API.
    if (hasUnsupportedWatchParameterShape(optionsObj)) {
        blockers.push('watch: watcher parameters must be migrated manually');
    }

    return blockers;
}

export function extractPropNamesFromText(optionsObj: ObjectLiteralExpression): string[] {
    const prop = optionsObj.getProperty('props');
    // Only prop names that can be extracted are used to resolve `this.<prop>`.
    // analyzePropsShape reports the unsupported shapes below; any prop it cannot
    // extract stays unresolved and drops its dependent members as follow-ups.
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return [];

    const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);

    // Example: `{ props: ['product', 'loading'] }`
    const arrayInit = pa.getInitializerIfKind(SyntaxKind.ArrayLiteralExpression);
    if (arrayInit) {
        return arrayInit
            .getElements()
            .filter((el) => el.isKind(SyntaxKind.StringLiteral))
            .map((el) => el.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue());
    }

    // Example: `{ props: { product: { type: Object, required: true } } }`
    const initializer = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);

    return (
        initializer
            ?.getProperties()
            // Examples: `{ props: { product: Object } }` and `{ props: { product() { return null; } } }`
            .filter((p) => p.isKind(SyntaxKind.PropertyAssignment) || p.isKind(SyntaxKind.MethodDeclaration))
            .map((p) =>
                getPropertyName(
                    p.isKind(SyntaxKind.PropertyAssignment)
                        ? p.asKindOrThrow(SyntaxKind.PropertyAssignment)
                        : p.asKindOrThrow(SyntaxKind.MethodDeclaration),
                ),
            ) ?? []
    );
}
