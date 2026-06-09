import type { ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import type { ExtractInjectPropsResult, InjectProp, UnsupportedInjectAnalysis } from './types';
import { getPropertyName, isSafeIdentifier, sanitizeTodoCommentText, serializeMethodLikeFunction } from './helpers';

export function extractInjectProps(optionsObj: ObjectLiteralExpression): ExtractInjectPropsResult {
    const prop = optionsObj.getProperty('inject');
    // TODO: Silent ignore: shorthand/non-property root `inject` declarations
    // are treated as absent instead of backing off like other unsupported
    // inject shapes.
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return { injectProps: [], unsupportedEntries: [] };

    const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);

    const arrayInit = pa.getInitializerIfKind(SyntaxKind.ArrayLiteralExpression);
    if (arrayInit) {
        const injectProps: InjectProp[] = [];
        const unsupportedEntries: string[] = [];

        arrayInit.getElements().forEach((el) => {
            if (!el.isKind(SyntaxKind.StringLiteral)) {
                unsupportedEntries.push(`${el.getText()}: unsupported inject entry`);
                return;
            }

            const key = el.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue();
            injectProps.push({
                localName: key,
                sourceKey: key,
            });
        });

        return {
            injectProps,
            unsupportedEntries,
        };
    }

    const objInit = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
    if (objInit) {
        const injectProps: InjectProp[] = [];
        const unsupportedEntries: string[] = [];

        objInit.getProperties().forEach((p) => {
            if (p.isKind(SyntaxKind.ShorthandPropertyAssignment)) {
                unsupportedEntries.push(`${getPropertyName(p)}: shorthand inject entries must be migrated manually`);
                return;
            }

            if (!p.isKind(SyntaxKind.PropertyAssignment)) {
                unsupportedEntries.push(`${p.getText()}: unsupported inject entry`);
                return;
            }

            const assignment = p.asKindOrThrow(SyntaxKind.PropertyAssignment);
            const localName = getPropertyName(assignment);
            const stringInit = assignment.getInitializerIfKind(SyntaxKind.StringLiteral);

            if (stringInit) {
                injectProps.push({
                    localName,
                    sourceKey: stringInit.getLiteralValue(),
                });
                return;
            }

            const objectInit = assignment.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);

            if (!objectInit) {
                unsupportedEntries.push(`${localName}: unsupported inject definition`);
                return;
            }

            const hasUnsupportedObjectMembers = objectInit.getProperties().some((member) => {
                if (member.isKind(SyntaxKind.PropertyAssignment)) {
                    const memberName = getPropertyName(member);

                    return memberName !== 'from' && memberName !== 'default';
                }

                return !(member.isKind(SyntaxKind.MethodDeclaration) && member.getName() === 'default');
            });

            if (hasUnsupportedObjectMembers) {
                unsupportedEntries.push(`${localName}: unsupported inject definition`);
                return;
            }

            const fromProp = objectInit.getProperty('from');
            if (fromProp && !fromProp.isKind(SyntaxKind.PropertyAssignment)) {
                unsupportedEntries.push(`${localName}: unsupported inject definition`);
                return;
            }

            const fromValue = fromProp?.isKind(SyntaxKind.PropertyAssignment)
                ? fromProp
                      .asKindOrThrow(SyntaxKind.PropertyAssignment)
                      .getInitializerIfKind(SyntaxKind.StringLiteral)
                      ?.getLiteralValue()
                : undefined;

            if (fromProp && fromValue === undefined) {
                unsupportedEntries.push(`${localName}: unsupported inject definition`);
                return;
            }

            const defaultProp = objectInit.getProperty('default');
            const defaultInitializer = defaultProp?.isKind(SyntaxKind.PropertyAssignment)
                ? defaultProp.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer()
                : undefined;
            const defaultMethod = defaultProp?.isKind(SyntaxKind.MethodDeclaration)
                ? defaultProp.asKindOrThrow(SyntaxKind.MethodDeclaration)
                : undefined;

            injectProps.push({
                localName,
                sourceKey: fromValue ?? localName,
                defaultValueText: defaultMethod ? serializeMethodLikeFunction(defaultMethod) : defaultInitializer?.getText(),
                // Vue inject() treats function defaults as values unless the
                // third argument marks them as factories.
                treatDefaultAsFactory:
                    defaultInitializer?.isKind(SyntaxKind.ArrowFunction) ||
                    defaultInitializer?.isKind(SyntaxKind.FunctionExpression) ||
                    defaultMethod !== undefined,
            });
        });

        return { injectProps, unsupportedEntries };
    }

    return { injectProps: [], unsupportedEntries: ['inject must be an array or object literal'] };
}

export function analyzeUnsupportedInjectEntries(optionsObj: ObjectLiteralExpression): UnsupportedInjectAnalysis {
    const { injectProps, unsupportedEntries } = extractInjectProps(optionsObj);
    const reasons = [
        ...unsupportedEntries.map((entry) => `inject: ${sanitizeTodoCommentText(entry)}`),
        ...injectProps
            .filter(({ localName }) => !isSafeIdentifier(localName))
            .map(({ localName }) => `inject: ${localName} is not a valid JavaScript identifier`),
    ];

    return { reasons: [...new Set(reasons)] };
}
