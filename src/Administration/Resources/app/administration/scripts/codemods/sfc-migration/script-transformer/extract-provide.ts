import type { Node as TsNode, ObjectLiteralElementLike, ObjectLiteralExpression } from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import { getPropertyName } from './helpers';
import type { ExtractProvideResult, ProvideEntry } from './types';

/**
 * Reads the `provide` option into the arguments of `provide(key, value)` calls.
 *
 * Only static shapes are translated: `provide() { return { … }; }` — also as a
 * function or arrow value — and a plain `provide: { … }` object, both with
 * identifier or string-literal keys. Anything else (computed keys, shorthand or
 * spread entries, accessors, statements before the return) can change the
 * provided keys per instance, so the whole option falls back to a manual TODO
 * rather than being migrated in part.
 */
export function extractProvideEntries(optionsObj: ObjectLiteralExpression): ExtractProvideResult {
    const prop = optionsObj.getProperty('provide');

    if (!prop) {
        return { provideEntries: [], requiresManualMigration: false };
    }

    const providedObject = getProvidedObjectLiteral(prop);

    if (!providedObject) {
        return { provideEntries: [], requiresManualMigration: true };
    }

    const provideEntries: ProvideEntry[] = [];

    for (const member of providedObject.getProperties()) {
        if (!member.isKind(SyntaxKind.PropertyAssignment)) {
            return { provideEntries: [], requiresManualMigration: true };
        }

        const assignment = member.asKindOrThrow(SyntaxKind.PropertyAssignment);
        const nameNode = assignment.getNameNode();
        const initializer = assignment.getInitializer();

        // Example: `{ provide() { return { [key]: value }; } }`
        if (!(Node.isIdentifier(nameNode) || Node.isStringLiteral(nameNode)) || !initializer) {
            return { provideEntries: [], requiresManualMigration: true };
        }

        provideEntries.push({ key: getPropertyName(assignment), valueText: initializer.getText() });
    }

    return { provideEntries, requiresManualMigration: false };
}

function getProvidedObjectLiteral(prop: ObjectLiteralElementLike): ObjectLiteralExpression | undefined {
    // Example: `{ provide() { return { registerItem: this.registerItem }; } }`
    if (prop.isKind(SyntaxKind.MethodDeclaration)) {
        const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);

        return method.isAsync() ? undefined : getSingleReturnedObjectLiteral(method.getBody());
    }

    if (!prop.isKind(SyntaxKind.PropertyAssignment)) {
        return undefined;
    }

    const initializer = prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();

    // Example: `{ provide: { registerItem } }`
    if (initializer?.isKind(SyntaxKind.ObjectLiteralExpression)) {
        return initializer.asKindOrThrow(SyntaxKind.ObjectLiteralExpression);
    }

    // Examples: `{ provide: function () { return { … }; } }` and `{ provide: () => ({ … }) }`
    const fn = initializer?.asKind(SyntaxKind.ArrowFunction) ?? initializer?.asKind(SyntaxKind.FunctionExpression);

    if (!fn || fn.isAsync()) {
        return undefined;
    }

    const body = fn.getBody();
    const parenthesized = body?.asKind(SyntaxKind.ParenthesizedExpression);

    return parenthesized
        ? parenthesized.getExpression().asKind(SyntaxKind.ObjectLiteralExpression)
        : getSingleReturnedObjectLiteral(body);
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
