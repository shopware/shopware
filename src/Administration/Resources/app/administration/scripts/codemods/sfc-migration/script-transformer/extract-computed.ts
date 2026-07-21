import type { ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import { extractInlineFunctionHandler } from './extract-function-handler';
import { isSimpleParameter } from './helpers';
import type { ComputedProp, ExtractComputedPropsResult } from './types';

export function extractComputedProps(optionsObj: ObjectLiteralExpression): ExtractComputedPropsResult {
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

    const result: ComputedProp[] = [];
    const unsupportedEntries: string[] = [];

    for (const prop of computedObj.getProperties()) {
        // Example: `{ computed: { productName() { return this.product.name; } } }`
        if (prop.isKind(SyntaxKind.MethodDeclaration)) {
            const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);
            result.push({ name: method.getName(), kind: 'getter', bodyText: method.getBodyText() ?? '' });
            continue;
        }

        if (prop.isKind(SyntaxKind.PropertyAssignment)) {
            const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);
            const initializer = pa.getInitializer();

            // Examples: `{ computed: { productName: function () {} } }` and `{ productName: () => '' }`
            if (initializer?.isKind(SyntaxKind.FunctionExpression) || initializer?.isKind(SyntaxKind.ArrowFunction)) {
                const { bodyText } = extractInlineFunctionHandler(initializer);
                result.push({ name: pa.getName(), kind: 'getter', bodyText });
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

                result.push({
                    name: pa.getName(),
                    kind: 'getter-setter',
                    getterBodyText: getter.getBodyText() ?? '',
                    setterParam: setterParam?.getName() ?? 'val',
                    setterBodyText: setter.getBodyText() ?? '',
                });
            } else if (getterProp?.isKind(SyntaxKind.MethodDeclaration)) {
                const getter = getterProp.asKindOrThrow(SyntaxKind.MethodDeclaration);
                result.push({
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

    return { computedProps: result, unsupportedEntries };
}
