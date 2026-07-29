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
        // Example: `{ methods: { save() { this.repository.save(); } } }`
        .getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);

    if (!methodsObj) {
        return { methodProps: [], unsupportedEntries: ['methods must be an object literal'] };
    }

    const result: MethodProp[] = [];
    const unsupportedEntries: string[] = [];

    for (const prop of methodsObj.getProperties()) {
        // Example: `{ methods: { save() { this.repository.save(); } } }`
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
            // Example: `{ methods: { save: async function () { await this.repository.save(); } } }`
            const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);
            const name = pa.getName();
            const initializer = pa.getInitializer();

            // Only inline functions or wrapper calls (e.g. debounce(fn)) carry a
            // body whose `this` we can rewrite. A bare external reference such as
            // `save: externalSave` loses its Options API instance binding when
            // emitted as `const save = externalSave;`, so it needs manual review.
            const isSupportedMethodValue =
                initializer?.isKind(SyntaxKind.FunctionExpression) ||
                initializer?.isKind(SyntaxKind.ArrowFunction) ||
                initializer?.isKind(SyntaxKind.CallExpression);

            if (!isSupportedMethodValue) {
                unsupportedEntries.push(`${name}: method value must be an inline function`);
                continue;
            }

            const initializerText = initializer.getText();
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
