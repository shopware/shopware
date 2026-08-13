import type { ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import { expandComputedSpread } from './expand-computed-spread';
import { extractInlineFunctionHandler } from './extract-function-handler';
import { isSimpleParameter } from './helpers';
import type { ComputedProp, ExtractComputedPropsResult } from './types';

/**
 * An object literal keeps the last value written for a key, and a spread writes
 * its keys where it stands. So a name an expansion produces and a name the
 * author wrote can collide, and the Options API resolved that by source order —
 * which is what the idiomatic "spread, then override one entry" pattern relies
 * on. Emitting both would instead make `dropDuplicatePublicNames` delete the
 * author's entry along with the generated one.
 *
 * Only collisions involving an expansion are resolved here. Two entries the
 * author wrote by hand are almost certainly a mistake, and stay loud.
 */
function resolveExpandedCollisions(entries: { prop: ComputedProp; isExpanded: boolean }[]): ComputedProp[] {
    const lastIndexByName = new Map<string, number>();
    entries.forEach(({ prop }, index) => lastIndexByName.set(prop.name, index));

    return entries
        .filter(({ prop, isExpanded }, index) => {
            const isShadowed = lastIndexByName.get(prop.name) !== index;

            return !isShadowed || !(isExpanded || entries[lastIndexByName.get(prop.name) as number].isExpanded);
        })
        .map(({ prop }) => prop);
}

export function extractComputedProps(
    optionsObj: ObjectLiteralExpression,
    trustedHelperNames: Set<string>,
    moduleBindingNames: Set<string>,
): ExtractComputedPropsResult {
    const computedProp = optionsObj.getProperty('computed');
    if (!computedProp) return { computedProps: [], unsupportedEntries: [] };
    // Shorthand or non-property `computed` cannot be read as an object literal.
    if (!computedProp.isKind(SyntaxKind.PropertyAssignment)) {
        return { computedProps: [], unsupportedEntries: ['computed must be an object literal'] };
    }

    const computedObj = computedProp
        .asKindOrThrow(SyntaxKind.PropertyAssignment)
        // Example: `{ computed: { productName() { return this.product.name; } } }`
        .getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
    if (!computedObj) {
        return { computedProps: [], unsupportedEntries: ['computed must be an object literal'] };
    }

    const entries: { prop: ComputedProp; isExpanded: boolean }[] = [];
    const unsupportedEntries: string[] = [];
    const declare = (prop: ComputedProp, isExpanded = false): void => {
        entries.push({ prop, isExpanded });
    };

    for (const prop of computedObj.getProperties()) {
        // Example: `{ computed: { ...mapPropertyErrors('product', ['name']) } }`
        if (prop.isKind(SyntaxKind.SpreadAssignment)) {
            const expanded = expandComputedSpread(
                prop.asKindOrThrow(SyntaxKind.SpreadAssignment),
                trustedHelperNames,
                moduleBindingNames,
            );

            if (expanded === null) {
                unsupportedEntries.push(`${prop.getText()}: unsupported computed entry`);
            } else {
                expanded.forEach((entry) => declare(entry, true));
            }

            continue;
        }

        // Example: `{ computed: { productName() { return this.product.name; } } }`
        if (prop.isKind(SyntaxKind.MethodDeclaration)) {
            const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);
            declare({ name: method.getName(), kind: 'getter', bodyText: method.getBodyText() ?? '' });
            continue;
        }

        if (prop.isKind(SyntaxKind.PropertyAssignment)) {
            const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);
            const initializer = pa.getInitializer();

            // Examples: `{ computed: { productName: function () {} } }` and `{ productName: () => '' }`
            if (initializer?.isKind(SyntaxKind.FunctionExpression) || initializer?.isKind(SyntaxKind.ArrowFunction)) {
                const { bodyText } = extractInlineFunctionHandler(initializer);
                declare({ name: pa.getName(), kind: 'getter', bodyText });
                continue;
            }

            // Example: `{ computed: { productName: { get() {}, set(value) {} } } }`
            const innerObj = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
            if (!innerObj) {
                unsupportedEntries.push(`${pa.getName()}: unsupported computed definition`);
                continue;
            }

            const getterProp = innerObj.getProperty('get');
            const setterProp = innerObj.getProperty('set');

            // Example: `{ computed: { productName: { get() {}, set(value) {} } } }`
            if (getterProp?.isKind(SyntaxKind.MethodDeclaration) && setterProp?.isKind(SyntaxKind.MethodDeclaration)) {
                const getter = getterProp.asKindOrThrow(SyntaxKind.MethodDeclaration);
                const setter = setterProp.asKindOrThrow(SyntaxKind.MethodDeclaration);
                const setterParam = setter.getParameters()[0];

                // getName() drops default values, rest syntax, and destructuring
                // from the setter parameter, so those shapes must be migrated by
                // hand instead of emitting a parameter list that changes meaning.
                if (setterParam && !isSimpleParameter(setterParam)) {
                    unsupportedEntries.push(`${pa.getName()}: computed setter parameter must be migrated manually`);
                    continue;
                }

                declare({
                    name: pa.getName(),
                    kind: 'getter-setter',
                    getterBodyText: getter.getBodyText() ?? '',
                    setterParam: setterParam?.getName() ?? 'val',
                    setterBodyText: setter.getBodyText() ?? '',
                });
            } else if (getterProp?.isKind(SyntaxKind.MethodDeclaration)) {
                const getter = getterProp.asKindOrThrow(SyntaxKind.MethodDeclaration);
                declare({
                    name: pa.getName(),
                    kind: 'getter',
                    bodyText: getter.getBodyText() ?? '',
                });
            } else {
                unsupportedEntries.push(`${pa.getName()}: unsupported computed definition`);
            }

            continue;
        }

        unsupportedEntries.push(`${prop.getText()}: unsupported computed entry`);
    }

    return { computedProps: resolveExpandedCollisions(entries), unsupportedEntries };
}
