import type { Node as TsNode, ObjectLiteralElementLike, ObjectLiteralExpression } from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import { getPropertyName, sanitizeTodoCommentText } from './helpers';
import type { ExtractProvideResult, ProvideEntry } from './types';

const UNSUPPORTED_SHAPE_REASON =
    'only a plain object or a non-arrow method returning an object literal can be mapped to provide(key, value) calls';

/**
 * Reads the `provide` option into the arguments of `provide(key, value)` calls.
 *
 * Only static shapes are translated: `provide() { return { … }; }` — also as a
 * `function` value — and a plain `provide: { … }` object, both with identifier
 * or string-literal keys. Everything else keeps a manual TODO carrying the
 * reason, instead of being migrated in part:
 *
 * - An arrow value never sees the instance. Vue applies the option with
 *   `provideOptions.call(publicThis)`, which an arrow ignores, so rewriting its
 *   `this` would change what the component provides.
 * - An async or generator `provide()` provides none of the listed keys: Vue
 *   reads the own keys of the returned promise or generator object.
 * - Computed keys, shorthand or spread entries, accessors, and statements before
 *   the `return` can change the provided keys per instance.
 */
export function extractProvideEntries(optionsObj: ObjectLiteralExpression): ExtractProvideResult {
    const prop = optionsObj.getProperty('provide');

    if (!prop) {
        return { provideEntries: [], unsupportedReason: null };
    }

    const providedObject = getProvidedObjectLiteral(prop);

    if (!providedObject) {
        return { provideEntries: [], unsupportedReason: UNSUPPORTED_SHAPE_REASON };
    }

    const provideEntries: ProvideEntry[] = [];

    for (const member of providedObject.getProperties()) {
        if (!member.isKind(SyntaxKind.PropertyAssignment)) {
            return unsupportedEntry(member);
        }

        const assignment = member.asKindOrThrow(SyntaxKind.PropertyAssignment);
        const nameNode = assignment.getNameNode();
        const initializer = assignment.getInitializer();

        // Example: `{ provide() { return { [key]: value }; } }`
        if (!initializer || !(Node.isIdentifier(nameNode) || Node.isStringLiteral(nameNode))) {
            return unsupportedEntry(member);
        }

        provideEntries.push({ key: getPropertyName(assignment), valueText: initializer.getText() });
    }

    return { provideEntries, unsupportedReason: null };
}

function unsupportedEntry(member: ObjectLiteralElementLike): ExtractProvideResult {
    return {
        provideEntries: [],
        unsupportedReason: `${sanitizeTodoCommentText(member.getText())}: unsupported provide entry`,
    };
}

function getProvidedObjectLiteral(prop: ObjectLiteralElementLike): ObjectLiteralExpression | undefined {
    // Example: `{ provide() { return { registerItem: this.registerItem }; } }`
    if (prop.isKind(SyntaxKind.MethodDeclaration)) {
        const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);

        return method.isAsync() || method.isGenerator() ? undefined : getSingleReturnedObjectLiteral(method.getBody());
    }

    if (!prop.isKind(SyntaxKind.PropertyAssignment)) {
        return undefined;
    }

    const initializer = prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();

    // Example: `{ provide: { registerItem: registerItem } }`
    if (initializer?.isKind(SyntaxKind.ObjectLiteralExpression)) {
        return initializer.asKindOrThrow(SyntaxKind.ObjectLiteralExpression);
    }

    // Example: `{ provide: function () { return { … }; } }`. Arrow values are
    // rejected here — see the note on the exported function.
    const fn = initializer?.asKind(SyntaxKind.FunctionExpression);

    if (!fn || fn.isAsync() || fn.isGenerator()) {
        return undefined;
    }

    return getSingleReturnedObjectLiteral(fn.getBody());
}

/**
 * The provided object must be the only thing the function does. Statements
 * before the return can compute keys or values, which the emitted `provide()`
 * calls would not reproduce.
 */
function getSingleReturnedObjectLiteral(body: TsNode | undefined): ObjectLiteralExpression | undefined {
    const statements = body?.asKind(SyntaxKind.Block)?.getStatements() ?? [];

    if (statements.length !== 1) {
        return undefined;
    }

    return statements[0].asKind(SyntaxKind.ReturnStatement)?.getExpression()?.asKind(SyntaxKind.ObjectLiteralExpression);
}
