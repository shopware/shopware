import type { ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import type { DataProp } from './types';

export function extractDataProps(optionsObj: ObjectLiteralExpression): DataProp[] {
    const dataProp = optionsObj.getProperty('data');
    if (!dataProp) return [];

    let returnExpr: ObjectLiteralExpression | undefined;

    if (dataProp.isKind(SyntaxKind.MethodDeclaration)) {
        const body = dataProp.asKindOrThrow(SyntaxKind.MethodDeclaration).getBody();
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

    if (!returnExpr) return [];

    return returnExpr
        .getProperties()
        .filter((p) => p.isKind(SyntaxKind.PropertyAssignment))
        .map((p) => p.asKindOrThrow(SyntaxKind.PropertyAssignment))
        .map((p) => ({
            name: p.getName(),
            valueText: p.getInitializer()?.getText() ?? 'undefined',
        }));
}
