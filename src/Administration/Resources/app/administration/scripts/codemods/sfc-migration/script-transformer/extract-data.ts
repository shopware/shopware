import type { ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import type { DataProp, ExtractDataPropsResult } from './types';

export function extractDataProps(optionsObj: ObjectLiteralExpression): ExtractDataPropsResult {
    const dataProp = optionsObj.getProperty('data');
    if (!dataProp) return { dataProps: [], unsupportedEntries: [] };

    let returnExpr: ObjectLiteralExpression | undefined;

    if (dataProp.isKind(SyntaxKind.MethodDeclaration)) {
        const body = dataProp.asKindOrThrow(SyntaxKind.MethodDeclaration).getBody();
        // TODO: Silent ignore: getDescendantsOfKind can pick nested returns,
        // so helper function return objects may be migrated as component data.
        const returnStmt = body?.getDescendantsOfKind(SyntaxKind.ReturnStatement)[0];
        returnExpr = returnStmt?.getExpression()?.isKind(SyntaxKind.ObjectLiteralExpression)
            ? returnStmt.getExpression()!.asKindOrThrow(SyntaxKind.ObjectLiteralExpression)
            : undefined;
    } else if (dataProp.isKind(SyntaxKind.PropertyAssignment)) {
        const init = dataProp.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();
        if (init?.isKind(SyntaxKind.ArrowFunction) || init?.isKind(SyntaxKind.FunctionExpression)) {
            const body = init.isKind(SyntaxKind.ArrowFunction)
                ? init.asKindOrThrow(SyntaxKind.ArrowFunction).getBody()
                : init.asKindOrThrow(SyntaxKind.FunctionExpression).getBody();
            if (body?.isKind(SyntaxKind.ParenthesizedExpression)) {
                const inner = body.asKindOrThrow(SyntaxKind.ParenthesizedExpression).getExpression();
                returnExpr = inner.isKind(SyntaxKind.ObjectLiteralExpression)
                    ? inner.asKindOrThrow(SyntaxKind.ObjectLiteralExpression)
                    : undefined;
            } else if (body?.isKind(SyntaxKind.Block)) {
                const returnStmt = body.asKindOrThrow(SyntaxKind.Block).getDescendantsOfKind(SyntaxKind.ReturnStatement)[0];
                returnExpr = returnStmt?.getExpression()?.isKind(SyntaxKind.ObjectLiteralExpression)
                    ? returnStmt.getExpression()!.asKindOrThrow(SyntaxKind.ObjectLiteralExpression)
                    : undefined;
            }
        }
    }

    // TODO: Silent ignore: unsupported data declarations or non-object return
    // expressions are treated as no data instead of marking the component
    // partially migratable.
    if (!returnExpr) return { dataProps: [], unsupportedEntries: [] };

    const dataProps: DataProp[] = [];
    const unsupportedEntries: string[] = [];

    returnExpr.getProperties().forEach((p) => {
        if (p.isKind(SyntaxKind.PropertyAssignment)) {
            const prop = p.asKindOrThrow(SyntaxKind.PropertyAssignment);

            // TODO: Silent ignore: data initializers can call component methods
            // through `this`; after migration those methods are declared later
            // in setup, which can create non-equivalent execution order.
            dataProps.push({
                name: prop.getName(),
                valueText: prop.getInitializer()?.getText() ?? 'undefined',
            });
            return;
        }

        if (p.isKind(SyntaxKind.ShorthandPropertyAssignment)) {
            unsupportedEntries.push(`${p.getName()}: shorthand data entries must be migrated manually`);
            return;
        }

        if (p.isKind(SyntaxKind.SpreadAssignment)) {
            unsupportedEntries.push(`${p.getText()}: spread data entries must be migrated manually`);
            return;
        }

        unsupportedEntries.push(`${p.getText()}: unsupported data entry`);
    });

    return {
        dataProps,
        unsupportedEntries,
    };
}
