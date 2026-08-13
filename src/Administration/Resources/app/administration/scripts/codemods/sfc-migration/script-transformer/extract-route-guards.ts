import type { ObjectLiteralElementLike, ObjectLiteralExpression } from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import type { ExtractRouteGuardsResult, RouteGuard } from './types';

/**
 * The in-component navigation guards vue-router also ships as composables.
 * `beforeRouteEnter` is deliberately absent: it runs before the component
 * instance exists, so there is no setup call to register it from and no
 * composable for it.
 */
const ROUTE_GUARD_COMPOSABLES: Record<string, string> = {
    beforeRouteLeave: 'onBeforeRouteLeave',
    beforeRouteUpdate: 'onBeforeRouteUpdate',
};

/** The `vue-router` imports the generated route guards can need. */
export const ROUTE_GUARD_COMPOSABLE_NAMES = Object.values(ROUTE_GUARD_COMPOSABLES);

export function isRouteGuardOptionName(name: string): boolean {
    return name in ROUTE_GUARD_COMPOSABLES;
}

/**
 * Reads `beforeRouteLeave` / `beforeRouteUpdate` as guards to re-register with
 * their composable. Only the method-shorthand form is migrated, for the same
 * reason lifecycle hooks are: a function value's `this` binding is not the one
 * the rewrite assumes.
 */
export function extractRouteGuards(optionsObj: ObjectLiteralExpression): ExtractRouteGuardsResult {
    const routeGuards: RouteGuard[] = [];
    const unsupportedEntries: string[] = [];

    for (const prop of optionsObj.getProperties()) {
        // A quoted or bracketed key names the same option as the shorthand does,
        // so it is read the same way here rather than through `getName()`.
        const name = readPropertyName(prop);

        if (!name || !isRouteGuardOptionName(name)) {
            continue;
        }

        if (prop.isKind(SyntaxKind.MethodDeclaration)) {
            const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);

            routeGuards.push({
                optionName: name,
                composableName: ROUTE_GUARD_COMPOSABLES[name],
                paramsText: method
                    .getParameters()
                    .map((param) => param.getText())
                    .join(', '),
                bodyText: method.getBodyText() ?? '',
                isAsync: method.isAsync(),
            });

            continue;
        }

        // Every other shape is reported, not only the property assignment and the
        // shorthand: these two options were taken off the unsupported top-level
        // list, so nothing else reports them, and an accessor
        // (`get beforeRouteLeave()`) would otherwise vanish from the output with
        // no TODO at all — and `--delete-originals` would then remove the source.
        unsupportedEntries.push(`${name}: route guard must be defined as a method to be migrated`);
    }

    // An object literal keeps the last method written for a key, so two
    // `beforeRouteLeave` methods are one guard — the second. Emitting both
    // composables would register a guard the component never had. Same rule the
    // computed expansion uses for a repeated key.
    const lastByOptionName = new Map(
        routeGuards.map((guard, index) => [
            guard.optionName,
            index,
        ]),
    );

    return {
        routeGuards: routeGuards.filter((guard, index) => lastByOptionName.get(guard.optionName) === index),
        unsupportedEntries,
    };
}

function readPropertyName(prop: ObjectLiteralElementLike): string | undefined {
    if (prop.isKind(SyntaxKind.SpreadAssignment)) {
        return undefined;
    }

    const nameNode = prop.getNameNode();

    if (Node.isStringLiteral(nameNode) || Node.isNumericLiteral(nameNode)) {
        return nameNode.getLiteralText();
    }

    if (Node.isComputedPropertyName(nameNode)) {
        const expression = nameNode.getExpression();

        return expression.isKind(SyntaxKind.StringLiteral) ? expression.getLiteralValue() : undefined;
    }

    return nameNode.getText();
}
