import type { Block, ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import type { DataProp, ExtractDataPropsResult } from './types';

export function extractDataProps(optionsObj: ObjectLiteralExpression): ExtractDataPropsResult {
    const dataProp = optionsObj.getProperty('data');
    if (!dataProp) return { dataProps: [], unsupportedEntries: [] };

    let returnExpr: ObjectLiteralExpression | undefined;

    if (dataProp.isKind(SyntaxKind.MethodDeclaration)) {
        const body = dataProp.asKindOrThrow(SyntaxKind.MethodDeclaration).getBody();
        returnExpr = getReturnedObjectLiteral(body);
    } else if (dataProp.isKind(SyntaxKind.PropertyAssignment)) {
        const init = dataProp.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();
        if (init?.isKind(SyntaxKind.ArrowFunction) || init?.isKind(SyntaxKind.FunctionExpression)) {
            const body = init.isKind(SyntaxKind.ArrowFunction)
                ? init.asKindOrThrow(SyntaxKind.ArrowFunction).getBody()
                : init.asKindOrThrow(SyntaxKind.FunctionExpression).getBody();
            if (body?.isKind(SyntaxKind.ObjectLiteralExpression)) {
                returnExpr = body.asKindOrThrow(SyntaxKind.ObjectLiteralExpression);
            } else if (body?.isKind(SyntaxKind.ParenthesizedExpression)) {
                const inner = body.asKindOrThrow(SyntaxKind.ParenthesizedExpression).getExpression();
                returnExpr = inner.isKind(SyntaxKind.ObjectLiteralExpression)
                    ? inner.asKindOrThrow(SyntaxKind.ObjectLiteralExpression)
                    : undefined;
            } else if (body?.isKind(SyntaxKind.Block)) {
                returnExpr = getReturnedObjectLiteral(body.asKindOrThrow(SyntaxKind.Block));
            }
        }
    }

    if (!returnExpr) {
        return {
            dataProps: [],
            unsupportedEntries: ['data must be a function returning an object literal'],
        };
    }

    const dataProps: DataProp[] = [];
    const unsupportedEntries: string[] = [];

    returnExpr.getProperties().forEach((p) => {
        if (p.isKind(SyntaxKind.PropertyAssignment)) {
            const prop = p.asKindOrThrow(SyntaxKind.PropertyAssignment);

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

function getReturnedObjectLiteral(body: Block | undefined): ObjectLiteralExpression | undefined {
    const returnStmt = body
        ?.getStatements()
        .find((statement) => statement.isKind(SyntaxKind.ReturnStatement))
        ?.asKindOrThrow(SyntaxKind.ReturnStatement);
    const returnExpr = returnStmt?.getExpression();

    return returnExpr?.isKind(SyntaxKind.ObjectLiteralExpression)
        ? returnExpr.asKindOrThrow(SyntaxKind.ObjectLiteralExpression)
        : undefined;
}
