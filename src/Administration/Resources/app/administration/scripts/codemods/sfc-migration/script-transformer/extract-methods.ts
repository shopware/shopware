import type { ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import type { ExtractMethodPropsResult, MethodProp } from './types';

export function extractMethodProps(optionsObj: ObjectLiteralExpression): ExtractMethodPropsResult {
    const methodsProp = optionsObj.getProperty('methods');

    if (!methodsProp) {
        return { methodProps: [], unsupportedEntries: [] };
    }

    if (!methodsProp.isKind(SyntaxKind.PropertyAssignment)) {
        return { methodProps: [], unsupportedEntries: ['methods must be an object literal'] };
    }

    const methodsObj = methodsProp
        .asKindOrThrow(SyntaxKind.PropertyAssignment)
        .getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);

    if (!methodsObj) {
        return { methodProps: [], unsupportedEntries: ['methods must be an object literal'] };
    }

    const result: MethodProp[] = [];
    const unsupportedEntries: string[] = [];

    for (const prop of methodsObj.getProperties()) {
        if (prop.isKind(SyntaxKind.MethodDeclaration)) {
            const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);
            result.push({
                name: method.getName(),
                paramsText: method
                    .getParameters()
                    .map((p) => p.getText())
                    .join(', '),
                bodyText: method.getBodyText() ?? '',
                isAsync: method.isAsync(),
            });
        } else if (prop.isKind(SyntaxKind.PropertyAssignment)) {
            const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);
            const name = pa.getName();
            const initializerText = pa.getInitializer()?.getText() ?? '';
            // TODO: Silent ignore: property-assignment methods can be external
            // references or wrapper expressions that depend on Vue instance
            // binding; they are emitted as setup constants without reporting
            // whether that binding is still equivalent.
            result.push({
                name,
                paramsText: '',
                bodyText: initializerText,
                isAsync: false,
                rawText: initializerText,
            });
        } else if (prop.isKind(SyntaxKind.ShorthandPropertyAssignment)) {
            unsupportedEntries.push(`${prop.getName()}: shorthand method entries must be migrated manually`);
        } else if (prop.isKind(SyntaxKind.SpreadAssignment)) {
            unsupportedEntries.push(`${prop.getText()}: spread method entries must be migrated manually`);
        } else {
            unsupportedEntries.push(`${prop.getText()}: unsupported method entry`);
        }
    }

    return { methodProps: result, unsupportedEntries };
}
