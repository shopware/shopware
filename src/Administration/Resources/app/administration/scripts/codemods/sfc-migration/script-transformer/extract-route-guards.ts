import type { ObjectLiteralElementLike, ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import { readOptionName } from './helpers';
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
    const unsupportedEntries: string[] = [];
    // An object literal keeps the last entry written for a key, whatever shape
    // each one has — so a getter after a method means there is no method guard
    // at all. Collecting per name first, then classifying the survivor, is the
    // only order that reproduces that.
    const lastByOptionName = new Map<string, ObjectLiteralElementLike>();

    for (const prop of optionsObj.getProperties()) {
        // A quoted or bracketed key names the same option as the shorthand does,
        // so it is read the same way here rather than through `getName()`.
        const name = readOptionName(prop);

        if (name !== undefined && isRouteGuardOptionName(name)) {
            lastByOptionName.set(name, prop);
        }
    }

    const routeGuards: RouteGuard[] = [];

    for (const [
        name,
        prop,
    ] of lastByOptionName) {
        const method = prop.asKind(SyntaxKind.MethodDeclaration);

        if (method) {
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

    return { routeGuards, unsupportedEntries };
}
