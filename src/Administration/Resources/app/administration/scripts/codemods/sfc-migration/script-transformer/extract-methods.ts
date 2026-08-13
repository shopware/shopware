import type { FunctionExpression, Node as TsNode, ObjectLiteralExpression } from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import { collectFreeIdentifierNames } from './ast';
import type { ExtractMethodPropsResult, MethodProp } from './types';

/**
 * Rewrites the `function` expressions inside a property-assignment method value
 * into arrows: `debounce(function onSave() { … })` becomes
 * `debounce(() => { … })`. The wrapper call itself is preserved — flattening it
 * would drop the debounce — and `this` inside is rewritten separately.
 *
 * Applied at extraction, so the shape checks and the emitter read the same text.
 * That matters for a *named* function expression: its name is a binding inside
 * itself, so `debounce(function onSave() { this.onSave(); })` looks like a
 * shadowed rewrite target until the name is gone, which it is here.
 *
 * Returns null when a wrapper cannot become an arrow without losing something,
 * so the caller reports it instead: an arrow has no `arguments`, and dropping a
 * function's name unbinds a self-recursive call. Only the header — up to the
 * body's `{` — is ever replaced, so nested wrappers stay convertible and string
 * literals are never touched.
 */
function normalizeMethodValueFunctions(node: TsNode): string | null {
    const text = node.getText();
    const nodeStart = node.getStart();
    const functionExpressions = [
        ...(Node.isFunctionExpression(node) ? [node] : []),
        ...node.getDescendantsOfKind(SyntaxKind.FunctionExpression),
    ];
    const replacements: { start: number; end: number; header: string }[] = [];

    for (const functionExpression of functionExpressions) {
        // A generator cannot be an arrow at all, so it is left as written — and
        // then needs none of the checks below.
        if (functionExpression.isGenerator()) {
            continue;
        }

        if (findUnconvertibleReason(functionExpression) !== null) {
            return null;
        }

        const body = functionExpression.getBody();
        const parameters = functionExpression
            .getParameters()
            .map((parameter) => parameter.getText())
            .join(', ');

        if (!body) {
            return null;
        }

        replacements.push({
            start: functionExpression.getStart() - nodeStart,
            end: body.getStart() - nodeStart,
            header: `${functionExpression.isAsync() ? 'async ' : ''}(${parameters}) => `,
        });
    }

    return replacements
        .sort((a, b) => b.start - a.start)
        .reduce((result, { start, end, header }) => result.slice(0, start) + header + result.slice(end), text);
}

/** Why a `function` expression cannot be re-emitted as an arrow, or null. */
function findUnconvertibleReason(functionExpression: FunctionExpression): string | null {
    const freeNames = collectFreeIdentifierNames(functionExpression);
    const name = functionExpression.getName();

    if (freeNames.has('arguments')) {
        return "wrapper function uses 'arguments'";
    }

    return name !== undefined && freeNames.has(name) ? 'wrapper function refers to its own name' : null;
}

/** The first reason a method value's wrappers could not be converted. */
function findWrapperReason(node: TsNode): string {
    const functionExpressions = [
        ...(Node.isFunctionExpression(node) ? [node] : []),
        ...node.getDescendantsOfKind(SyntaxKind.FunctionExpression),
    ];

    for (const functionExpression of functionExpressions) {
        const reason = functionExpression.isGenerator() ? null : findUnconvertibleReason(functionExpression);

        if (reason !== null) {
            return reason;
        }
    }

    return 'wrapper function could not be migrated';
}

export function extractMethodProps(
    optionsObj: ObjectLiteralExpression,
    moduleBindingNames: Set<string>,
): ExtractMethodPropsResult {
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
            // body whose `this` we can rewrite.
            const isInlineFunctionValue =
                initializer?.isKind(SyntaxKind.FunctionExpression) ||
                initializer?.isKind(SyntaxKind.ArrowFunction) ||
                initializer?.isKind(SyntaxKind.CallExpression);

            // Example: `{ methods: { getKey: get } }` with `const { get } = Shopware.Utils;`
            // at module level. The Options API resolves that name in module scope,
            // not on the instance, and the generated block inherits the very same
            // binding — so `const getKey = get;` is the same function. Any other
            // bare reference is unresolved and stays a manual follow-up.
            const isModuleBindingValue =
                initializer !== undefined &&
                initializer.isKind(SyntaxKind.Identifier) &&
                moduleBindingNames.has(initializer.getText());

            if (!initializer || !(isInlineFunctionValue || isModuleBindingValue)) {
                unsupportedEntries.push(`${name}: method value must be an inline function`);
                continue;
            }

            // Normalised here, not in the emitter, so the shape checks read the
            // same text that will be written.
            const initializerText = normalizeMethodValueFunctions(initializer);

            if (initializerText === null) {
                const reason = findWrapperReason(initializer);

                unsupportedEntries.push(`${name}: ${reason} and must be migrated manually`);
                continue;
            }

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
