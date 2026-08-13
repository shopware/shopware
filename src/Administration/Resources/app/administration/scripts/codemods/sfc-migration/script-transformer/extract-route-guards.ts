import type { ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
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
        if (prop.isKind(SyntaxKind.MethodDeclaration)) {
            const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);
            const composableName = ROUTE_GUARD_COMPOSABLES[method.getName()];

            if (composableName) {
                routeGuards.push({
                    optionName: method.getName(),
                    composableName,
                    paramsText: method
                        .getParameters()
                        .map((param) => param.getText())
                        .join(', '),
                    bodyText: method.getBodyText() ?? '',
                    isAsync: method.isAsync(),
                });
            }

            continue;
        }

        const name = prop.isKind(SyntaxKind.PropertyAssignment)
            ? prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getName()
            : prop.isKind(SyntaxKind.ShorthandPropertyAssignment)
              ? prop.asKindOrThrow(SyntaxKind.ShorthandPropertyAssignment).getName()
              : undefined;

        if (name && isRouteGuardOptionName(name)) {
            unsupportedEntries.push(`${name}: route guard must be defined as a method to be migrated`);
        }
    }

    return { routeGuards, unsupportedEntries };
}
