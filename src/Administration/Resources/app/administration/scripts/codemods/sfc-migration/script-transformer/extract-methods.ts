import type { ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import type { MethodProp } from './types';

export function extractMethodProps(optionsObj: ObjectLiteralExpression): MethodProp[] {
    const methodsProp = optionsObj.getProperty('methods');
    if (!methodsProp?.isKind(SyntaxKind.PropertyAssignment)) return [];

    const methodsObj = methodsProp
        .asKindOrThrow(SyntaxKind.PropertyAssignment)
        .getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
    if (!methodsObj) return [];

    const result: MethodProp[] = [];

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
            result.push({
                name,
                paramsText: '',
                bodyText: initializerText,
                isAsync: false,
                rawText: initializerText,
            });
        }
    }

    return result;
}
