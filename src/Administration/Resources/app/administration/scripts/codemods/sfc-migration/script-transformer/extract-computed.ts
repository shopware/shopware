import type { ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import { extractInlineFunctionHandler } from './extract-function-handler';
import type { ComputedProp, ExtractComputedPropsResult } from './types';

export function extractComputedProps(optionsObj: ObjectLiteralExpression): ExtractComputedPropsResult {
    const computedProp = optionsObj.getProperty('computed');
    // TODO: Silent ignore: shorthand/non-property `computed` declarations are
    // treated as absent instead of being reported as unsupported.
    if (!computedProp?.isKind(SyntaxKind.PropertyAssignment)) return { computedProps: [], unsupportedEntries: [] };

    const computedObj = computedProp
        .asKindOrThrow(SyntaxKind.PropertyAssignment)
        .getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
    if (!computedObj) {
        return { computedProps: [], unsupportedEntries: ['computed must be an object literal'] };
    }

    const result: ComputedProp[] = [];
    const unsupportedEntries: string[] = [];

    for (const prop of computedObj.getProperties()) {
        if (prop.isKind(SyntaxKind.MethodDeclaration)) {
            const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);
            result.push({ name: method.getName(), kind: 'getter', bodyText: method.getBodyText() ?? '' });
            continue;
        }

        if (prop.isKind(SyntaxKind.PropertyAssignment)) {
            const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);
            const initializer = pa.getInitializer();

            if (initializer?.isKind(SyntaxKind.FunctionExpression) || initializer?.isKind(SyntaxKind.ArrowFunction)) {
                const { bodyText } = extractInlineFunctionHandler(initializer);
                result.push({ name: pa.getName(), kind: 'getter', bodyText });
                continue;
            }

            const innerObj = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
            if (!innerObj) {
                unsupportedEntries.push(`${pa.getName()}: unsupported computed definition`);
                continue;
            }

            const getterProp = innerObj.getProperty('get');
            const setterProp = innerObj.getProperty('set');

            if (getterProp?.isKind(SyntaxKind.MethodDeclaration) && setterProp?.isKind(SyntaxKind.MethodDeclaration)) {
                const getter = getterProp.asKindOrThrow(SyntaxKind.MethodDeclaration);
                const setter = setterProp.asKindOrThrow(SyntaxKind.MethodDeclaration);

                result.push({
                    name: pa.getName(),
                    kind: 'getter-setter',
                    getterBodyText: getter.getBodyText() ?? '',
                    // TODO: Silent ignore: getName() drops default/rest/
                    // destructuring syntax from computed setter parameters.
                    setterParam: setter.getParameters()[0]?.getName() ?? 'val',
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
