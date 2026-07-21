import type { ArrowFunction, FunctionExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';

export function extractInlineFunctionHandler(handler: ArrowFunction | FunctionExpression): {
    paramsText: string;
    bodyText: string;
    isAsync: boolean;
} {
    const body = handler.getBody();

    return {
        isAsync: handler.isAsync(),
        paramsText: handler
            .getParameters()
            .map((param) => param.getText())
            .join(', '),
        // Example block body: `(value) => { this.load(value); }`; expression body: `(value) => value.id`.
        bodyText: body.isKind(SyntaxKind.Block)
            ? body
                  .getStatements()
                  .map((statement) => statement.getText())
                  .join('\n')
            : `return ${body.getText()};`,
    };
}
