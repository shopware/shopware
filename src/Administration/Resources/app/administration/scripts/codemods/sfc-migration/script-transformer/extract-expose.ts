import type { ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import { sanitizeTodoCommentText } from './helpers';
import type { ExtractExposeResult } from './types';

const UNSUPPORTED_SHAPE_REASON = 'only an array of string literals can be mapped to defineExpose({ … })';

/**
 * Reads the `expose` option into the names of the generated `defineExpose({ … })`
 * object.
 *
 * Only the array-of-string-literals form is translated. A computed list is
 * decided at runtime, and Vue reads `expose` once per instance, so a generated
 * `defineExpose` built from a guessed list would declare a different public
 * surface than the component did.
 *
 * An empty array is returned as an empty list without a reason: in the Options
 * API `expose: []` closes the instance completely, which is what a
 * `<script setup>` component already is.
 */
export function extractExposeNames(optionsObj: ObjectLiteralExpression): ExtractExposeResult {
    const prop = optionsObj.getProperty('expose');

    if (!prop) {
        return { exposeNames: [], unsupportedReason: null };
    }

    // Example: `{ expose: ['focus', 'isOpen'] }`
    const arrayLiteral = prop.isKind(SyntaxKind.PropertyAssignment)
        ? prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializerIfKind(SyntaxKind.ArrayLiteralExpression)
        : undefined;

    if (!arrayLiteral) {
        return { exposeNames: [], unsupportedReason: UNSUPPORTED_SHAPE_REASON };
    }

    const exposeNames: string[] = [];

    for (const element of arrayLiteral.getElements()) {
        // Example: `{ expose: [...baseExpose, methodName] }`
        if (!element.isKind(SyntaxKind.StringLiteral)) {
            return {
                exposeNames: [],
                unsupportedReason: `${sanitizeTodoCommentText(element.getText())}: unsupported expose entry`,
            };
        }

        exposeNames.push(element.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue());
    }

    return { exposeNames, unsupportedReason: null };
}
